<?php

namespace App\Http\Controllers\Back;

use App\{
    Models\Order,
    Models\Item,
    Models\PromoCode,
    Models\TrackOrder,
    Http\Controllers\Controller
};
use App\Helpers\CheckoutShippingHelper;
use App\Helpers\PriceHelper;
use App\Helpers\SmsHelper;
use App\Jobs\SendOrderTrackingEmailJob;
use App\Models\Notification;
use App\Services\EShipperService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    /**
     * Constructor Method.
     *
     * Setting Authentication
     *
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }



    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
      
        if($request->type){
            if($request->start_date && $request->end_date){
                $datas = $start_date = Carbon::parse($request->start_date);
                $end_date = Carbon::parse($request->end_date);
                $datas = Order::latest('id')->whereOrderStatus($request->type)->whereDate('created_at','>=',$start_date)->whereDate('created_at','<=',$end_date)->get();
            }else{
                $datas = Order::latest('id')->whereOrderStatus($request->type)->get();
            }
            
        }else{
            if($request->start_date && $request->end_date){
                $datas = $start_date = Carbon::parse($request->start_date);
                $end_date = Carbon::parse($request->end_date);
                $datas = Order::latest('id')->whereDate('created_at','>=',$start_date)->whereDate('created_at','<=',$end_date)->get();
            }else{
                $datas = Order::latest('id')->get();
            }
        }
        return view('back.order.index',compact('datas'));
    }

    
    public function edit($id)
    {
        $order = Order::findOrFail($id);
        $shipmentPreview = $this->buildShipmentPreview($order);

        return view('back.order.edit', compact('order', 'shipmentPreview'));
    }

    

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $previousTrackingNumber = $order->tracking_number;
        
        // Check if order_id is available
        if (Order::where('transaction_number', $request->transaction_number)->where('id', '!=', $id)->exists()) {
            return redirect()->route('back.order.index')->withErrors(__('Order ID already exists.'));
        }

        $payload = $request->all();
        $trackingNumber = trim((string) ($request->tracking_number ?? ''));

        if ($trackingNumber !== '' && $trackingNumber !== trim((string) $previousTrackingNumber)) {
            $payload['shipment_created_at'] = now();
            $payload['tracking_emailed_at'] = null;
        }

        $order->update($payload);

        if ($trackingNumber !== '' && $trackingNumber !== trim((string) $previousTrackingNumber)) {
            $sendAt = $order->created_at->copy()->addHours(12);

            if (now()->greaterThanOrEqualTo($sendAt)) {
                dispatch(new SendOrderTrackingEmailJob($order->id));
            } else {
                dispatch((new SendOrderTrackingEmailJob($order->id))->delay($sendAt));
            }
        }

        return redirect()->route('back.order.index')->withSuccess(__('Order Updated Successfully.'));
    }

    public function createShipment($id)
    {
        $order = Order::findOrFail($id);
        $service = app(EShipperService::class);
        $shipmentPreview = $this->buildShipmentPreview($order);

        if (!$service->canCreateShipments()) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors(__('Live shipment creation is disabled. Set ESHIPPER_ALLOW_LIVE_SHIPMENTS=true only when you are ready to create billable shipments.'));
        }

        if (!$shipmentPreview['ready']) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors(__('Shipment cannot be created until the address and package warnings are resolved.'));
        }

        $rateId = $shipmentPreview['shipping_method']['rate_id'] ?? null;
        if (!$rateId) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors(__('This order does not have a saved carrier rate ID, so shipment creation cannot continue.'));
        }

        try {
            $response = $service->createShipment($this->buildShipmentPayload($order, $shipmentPreview), $rateId);
            $shipmentMeta = $this->decodeJson($order->shipment_meta);
            $shipmentMeta['eshipper'] = $response;

            $trackingNumber = data_get($response, 'trackingNumber')
                ?: data_get($response, 'tracking.number')
                ?: data_get($response, 'tracking')
                ?: $order->tracking_number;

            $carrier = data_get($response, 'carrierName')
                ?: data_get($response, 'carrier.name')
                ?: ($shipmentPreview['shipping_method']['carrier'] ?? $order->shipping_carrier);

            $methodName = data_get($response, 'serviceName')
                ?: data_get($response, 'service.name')
                ?: ($shipmentPreview['shipping_method']['title'] ?? $order->shipping_method_name);

            $payload = [
                'shipment_provider' => 'eshipper',
                'shipping_method_code' => (string) $rateId,
                'shipping_method_name' => $methodName,
                'shipping_carrier' => $carrier,
                'shipment_meta' => json_encode($shipmentMeta),
                'shipment_created_at' => now(),
            ];

            if ($trackingNumber) {
                $payload['tracking_number'] = $trackingNumber;
                $payload['tracking_emailed_at'] = null;
            }

            $order->update($payload);

            if (!empty($payload['tracking_number'])) {
                $sendAt = $order->created_at->copy()->addHours(12);

                if (now()->greaterThanOrEqualTo($sendAt)) {
                    dispatch(new SendOrderTrackingEmailJob($order->id));
                } else {
                    dispatch((new SendOrderTrackingEmailJob($order->id))->delay($sendAt));
                }
            }

            return redirect()->route('back.order.edit', $order->id)
                ->withSuccess(__('Shipment created successfully.'));
        } catch (\Throwable $e) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors($e->getMessage());
        }
    }

    public function shipmentLabel($id)
    {
        $order = Order::findOrFail($id);
        $service = app(EShipperService::class);
        $shipmentMeta = $this->decodeJson($order->shipment_meta);
        $shipmentId = $this->extractShipmentId($shipmentMeta);

        if (!$shipmentId) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors(__('No saved shipment ID was found for this order yet.'));
        }

        try {
            $response = $service->getShipmentLabel($shipmentId);
            $contentType = $response->header('Content-Type', 'application/octet-stream');
            $disposition = $response->header('Content-Disposition');
            $filename = 'shipment-label-' . $order->transaction_number . '.pdf';

            if ($disposition && preg_match('/filename=\"?([^\";]+)\"?/i', $disposition, $matches)) {
                $filename = $matches[1];
            }

            return response($response->body(), $response->status())
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            return redirect()->route('back.order.edit', $order->id)
                ->withErrors($e->getMessage());
        }
    }

    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function invoice($id)
    {
        $order = Order::findOrfail($id);
        $cart = json_decode($order->cart, true);
        return view('back.order.invoice',compact('order','cart'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function printOrder($id)
    {
        $order = Order::findOrfail($id);
        $cart = json_decode($order->cart, true);
        return view('back.order.print',compact('order','cart'));
    }


    /**
     * Change the status for editing the specified resource.
     *
     * @param  int  $id
     * @param  string  $field
     * @param  string  $value
     * @return \Illuminate\Http\Response
     */
    public function status($id,$field,$value)
    {

        $order = Order::find($id);
        if($field == 'payment_status'){
            if($order['payment_status'] == 'Paid'){
                return redirect()->route('back.order.index')->withErrors(__('Order is already paid.'));
            }
        }
        if($field == 'order_status'){
            if($order['order_status'] == 'Delivered'){
                return redirect()->route('back.order.index')->withErrors(__('Order is already Delivered.'));
            }
        }
        $order->update([$field => $value]);
        if($order->payment_status == 'Paid'){
            $this->setPromoCode($order);
        }
        $this->setTrackOrder($order);
        
        $sms = new SmsHelper();
        $user_number = $order->user->phone;
        if($user_number){
            $sms->SendSms($user_number,"'order_status'",$order->transaction_number);
        }
       
        return redirect()->route('back.order.index')->withSuccess(__('Status Updated Successfully.'));
    }

    /**
     * Custom Function
     */
    public function setTrackOrder($order)
    {

        if($order->order_status == 'In Progress'){
            if(!TrackOrder::whereOrderId($order->id)->whereTitle('In Progress')->exists()){
                TrackOrder::create([
                    'title' => 'In Progress',
                    'order_id' => $order->id
                ]);
            }
        }
        if($order->order_status == 'Canceled'){
            if(!TrackOrder::whereOrderId($order->id)->whereTitle('Canceled')->exists()){

                if(!TrackOrder::whereOrderId($order->id)->whereTitle('In Progress')->exists()){
                    TrackOrder::create([
                        'title' => 'In Progress',
                        'order_id' => $order->id
                    ]);
                }
                if(!TrackOrder::whereOrderId($order->id)->whereTitle('Delivered')->exists()){
                    TrackOrder::create([
                        'title' => 'Delivered',
                        'order_id' => $order->id
                    ]);
                }

                if(!TrackOrder::whereOrderId($order->id)->whereTitle('Canceled')->exists()){
                    TrackOrder::create([
                        'title' => 'Canceled',
                        'order_id' => $order->id
                    ]);
                }


            }
        }

        if($order->order_status == 'Delivered'){

            if(!TrackOrder::whereOrderId($order->id)->whereTitle('In Progress')->exists()){
                TrackOrder::create([
                    'title' => 'In Progress',
                    'order_id' => $order->id
                ]);
            }

            if(!TrackOrder::whereOrderId($order->id)->whereTitle('Delivered')->exists()){
                TrackOrder::create([
                    'title' => 'Delivered',
                    'order_id' => $order->id
                ]);
            }
        }
    }


    public function setPromoCode($order)
    {

        $discount = json_decode($order->discount, true);
        if($discount != null){
            $code = PromoCode::find($discount['code']['id']);
            $code->no_of_times--;
            $code->update();
        }
    }


    public function delete($id)
    {
        $order = Order::findOrFail($id);
        $order->tranaction->delete();
        if(Notification::where('order_id',$id)->exists()){
            Notification::where('order_id',$id)->delete();
        }
        if(count($order->tracks_data)>0){
            foreach($order->tracks_data as $track){
                $track->delete();
            }
        }
        $order->delete();
        return redirect()->back()->withSuccess(__('Order Deleted Successfully.'));
    }

    protected function buildShipmentPreview(Order $order)
    {
        $shippingAddress = $this->decodeJson($order->shipping_info);
        $billingAddress = $this->decodeJson($order->billing_info);
        $shippingMethod = $this->decodeJson($order->shipping);
        $cart = $this->decodeJson($order->cart);
        $warnings = [];
        $packages = [];

        if (empty($shippingAddress)) {
            $warnings[] = __('Shipping address is missing from this order.');
        }

        if (($shippingMethod['source'] ?? null) === 'flat_rate') {
            $warnings[] = __('This order used the CAD 25 flat-rate fallback because at least one product category is missing package data.');
        }

        if (($shippingMethod['source'] ?? null) === 'free_shipping') {
            $warnings[] = __('This order qualified for free shipping, so there is no carrier rate attached yet.');
        }

        foreach ($cart as $itemId => $line) {
            if (($line['item_type'] ?? null) !== 'normal') {
                continue;
            }

            $item = Item::find($itemId);
            if (!$item) {
                $warnings[] = __('Product #:id no longer exists, so package details must be added manually.', ['id' => $itemId]);
                continue;
            }

            $packageData = $this->packageDataForItem($item);

            if (!$packageData) {
                $warnings[] = __('Package dimensions or weight are missing for product ":name". Add item package data or category defaults.', ['name' => $item->name]);
                continue;
            }

            for ($i = 0; $i < (int) ($line['qty'] ?? 0); $i++) {
                $packages[] = [
                    'description' => $item->name,
                    'length' => $packageData['length'],
                    'width' => $packageData['width'],
                    'height' => $packageData['height'],
                    'weight' => $packageData['weight'],
                ];
            }
        }

        if (empty($packages) && empty($warnings)) {
            $warnings[] = __('No shippable package data was found for this order.');
        }

        if (!empty($shippingAddress) && strtoupper((string) ($shippingAddress['ship_country'] ?? '')) !== 'CA') {
            $warnings[] = __('This order shipping country is not Canada, so it is outside the current shipping rule set.');
        }

        $shipmentMeta = $this->decodeJson($order->shipment_meta);
        $shipmentId = $this->extractShipmentId($shipmentMeta);
        $labelUrl = $this->extractLabelUrl($shipmentMeta);

        return [
            'ready' => empty($warnings) && !empty($packages),
            'warnings' => array_values(array_unique($warnings)),
            'shipping_method' => [
                'title' => $order->shipping_method_name ?: ($shippingMethod['title'] ?? null),
                'carrier' => $order->shipping_carrier ?: ($shippingMethod['carrier_name'] ?? null),
                'source' => $shippingMethod['source'] ?? null,
                'price' => isset($shippingMethod['price']) ? PriceHelper::adminCurrencyPrice((float) $shippingMethod['price']) : null,
                'rate_id' => $shippingMethod['rate_id'] ?? null,
                'service_id' => $shippingMethod['service_id'] ?? null,
                'quote_uuid' => $shippingMethod['quote_uuid'] ?? null,
            ],
            'origin' => [
                'company' => config('services.eshipper.origin_company'),
                'contact' => config('services.eshipper.origin_contact'),
                'email' => config('services.eshipper.origin_email'),
                'phone' => config('services.eshipper.origin_phone'),
                'address1' => config('services.eshipper.origin_address1'),
                'address2' => config('services.eshipper.origin_address2'),
                'city' => config('services.eshipper.origin_city'),
                'province' => CheckoutShippingHelper::customerProvince(config('services.eshipper.origin_province')),
                'country' => config('services.eshipper.origin_country', 'CA'),
                'zip' => $this->normalizePostalCode(config('services.eshipper.origin_postal')),
            ],
            'destination' => [
                'attention' => trim(((string) ($shippingAddress['ship_first_name'] ?? '')) . ' ' . ((string) ($shippingAddress['ship_last_name'] ?? ''))),
                'company' => $shippingAddress['ship_company'] ?? null,
                'email' => $shippingAddress['ship_email'] ?? ($billingAddress['bill_email'] ?? null),
                'phone' => $shippingAddress['ship_phone'] ?? ($billingAddress['bill_phone'] ?? null),
                'address1' => $shippingAddress['ship_address1'] ?? null,
                'address2' => $shippingAddress['ship_address2'] ?? null,
                'city' => $shippingAddress['ship_city'] ?? null,
                'province' => isset($shippingAddress['ship_province']) ? CheckoutShippingHelper::customerProvince($shippingAddress['ship_province']) : null,
                'country' => $shippingAddress['ship_country'] ?? null,
                'zip' => isset($shippingAddress['ship_zip']) ? $this->normalizePostalCode($shippingAddress['ship_zip']) : null,
            ],
            'packages' => $packages,
            'shipment' => [
                'provider' => $order->shipment_provider,
                'created_at' => $order->shipment_created_at,
                'tracking_number' => $order->tracking_number,
                'shipment_id' => $shipmentId,
                'label_url' => $labelUrl,
                'has_label_route' => !empty($shipmentId),
                'meta' => $shipmentMeta,
            ],
        ];
    }

    protected function buildShipmentPayload(Order $order, array $shipmentPreview)
    {
        $shippingAddress = $this->decodeJson($order->shipping_info);

        return [
            'from' => [
                'attention' => $shipmentPreview['origin']['contact'] ?? config('services.eshipper.origin_contact'),
                'company' => $shipmentPreview['origin']['company'] ?? config('services.eshipper.origin_company'),
                'address1' => $shipmentPreview['origin']['address1'] ?? config('services.eshipper.origin_address1'),
                'address2' => $shipmentPreview['origin']['address2'] ?? config('services.eshipper.origin_address2'),
                'city' => $shipmentPreview['origin']['city'] ?? config('services.eshipper.origin_city'),
                'province' => $shipmentPreview['origin']['province'] ?? CheckoutShippingHelper::customerProvince(config('services.eshipper.origin_province')),
                'country' => $shipmentPreview['origin']['country'] ?? config('services.eshipper.origin_country', 'CA'),
                'zip' => $shipmentPreview['origin']['zip'] ?? $this->normalizePostalCode(config('services.eshipper.origin_postal')),
                'email' => $shipmentPreview['origin']['email'] ?? config('services.eshipper.origin_email'),
                'phone' => $shipmentPreview['origin']['phone'] ?? config('services.eshipper.origin_phone'),
            ],
            'to' => [
                'attention' => $shipmentPreview['destination']['attention'] ?? trim(((string) ($shippingAddress['ship_first_name'] ?? '')) . ' ' . ((string) ($shippingAddress['ship_last_name'] ?? ''))),
                'company' => $shipmentPreview['destination']['company'] ?? ($shippingAddress['ship_company'] ?? null),
                'address1' => $shipmentPreview['destination']['address1'] ?? ($shippingAddress['ship_address1'] ?? null),
                'address2' => $shipmentPreview['destination']['address2'] ?? ($shippingAddress['ship_address2'] ?? null),
                'city' => $shipmentPreview['destination']['city'] ?? ($shippingAddress['ship_city'] ?? null),
                'province' => $shipmentPreview['destination']['province'] ?? (isset($shippingAddress['ship_province']) ? CheckoutShippingHelper::customerProvince($shippingAddress['ship_province']) : null),
                'country' => $shipmentPreview['destination']['country'] ?? ($shippingAddress['ship_country'] ?? 'CA'),
                'zip' => $shipmentPreview['destination']['zip'] ?? (isset($shippingAddress['ship_zip']) ? $this->normalizePostalCode($shippingAddress['ship_zip']) : null),
                'email' => $shipmentPreview['destination']['email'] ?? ($shippingAddress['ship_email'] ?? null),
                'phone' => $shipmentPreview['destination']['phone'] ?? ($shippingAddress['ship_phone'] ?? null),
            ],
            'scheduledShipDate' => now()->format('Y-m-d H:i'),
            'packagingUnit' => config('services.eshipper.packaging_unit', 'Imperial'),
            'packages' => [
                'type' => 'Package',
                'packages' => $shipmentPreview['packages'],
            ],
            'currency' => config('services.eshipper.currency', 'CAD'),
            'insidePickup' => false,
            'insuranceType' => 'NONE',
            'saturdayPickupRequired' => false,
            'reference' => $order->transaction_number,
        ];
    }

    protected function decodeJson($value)
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function normalizePostalCode($postalCode)
    {
        return strtoupper(str_replace(' ', '', trim((string) $postalCode)));
    }

    protected function packageDataForItem(Item $item)
    {
        $source = $item;

        if (
            !$source->package_length ||
            !$source->package_width ||
            !$source->package_height ||
            !$source->package_weight
        ) {
            $source = $item->category;
        }

        if (
            !$source ||
            !$source->package_length ||
            !$source->package_width ||
            !$source->package_height ||
            !$source->package_weight
        ) {
            return null;
        }

        return [
            'length' => (float) $source->package_length,
            'width' => (float) $source->package_width,
            'height' => (float) $source->package_height,
            'weight' => (float) $source->package_weight,
        ];
    }

    protected function extractShipmentId(array $shipmentMeta)
    {
        return data_get($shipmentMeta, 'eshipper.id')
            ?: data_get($shipmentMeta, 'eshipper.orderId')
            ?: data_get($shipmentMeta, 'eshipper.shipmentId')
            ?: data_get($shipmentMeta, 'eshipper.order.id')
            ?: data_get($shipmentMeta, 'eshipper.shipment.id');
    }

    protected function extractLabelUrl(array $shipmentMeta)
    {
        return data_get($shipmentMeta, 'eshipper.labelUrl')
            ?: data_get($shipmentMeta, 'eshipper.label.url')
            ?: data_get($shipmentMeta, 'eshipper.documents.label')
            ?: data_get($shipmentMeta, 'eshipper.links.label');
    }

}
