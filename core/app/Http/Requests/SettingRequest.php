<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if(isset($this->is_validate)){
            return [
                'title' => 'required|max:255',
                'footer_address' => 'required|max:255',
                'footer_phone' => 'required|max:255',
                'footer_email' => 'required|max:255',
                'copy_right' => 'required|max:255',
                'friday_start' => 'required|max:255',
                'friday_end' => 'required|max:255',
                'working_days_from_to' => 'required|max:255',
                'logo' => 'mimes:jpeg,jpg,png,svg,webp',
                'meta_image' => 'mimes:jpeg,jpg,png,svg,webp',
                'loader' => 'mimes:jpeg,jpg,png,svg,gif',
                'favicon' => 'mimes:jpeg,jpg,png,svg,ico',
                'feature_image' => 'mimes:jpeg,jpg,png,svg,webp',
                'home_background' => 'mimes:jpeg,jpg,png,svg,webp',
                'breadcumb_background' => 'mimes:jpeg,jpg,png,svg,webp',
                'footer_background' => 'mimes:jpeg,jpg,png,svg,webp',
                'popup_banner' => 'mimes:jpeg,jpg,png,svg,webp',
                'footer_gateway_img' => 'mimes:jpeg,jpg,png,svg,webp'
            ];
        }else{
            return [

            ];
        }

    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'logo.mimes'    => __('Logo Image type must be jpg,jpeg,png,svg,webp.'),
            'loader.mimes'    => __('Loader Image type must be jpg,jpeg,png,svg,gif.'),
            'favicon.mimes'    => __('Favicon Image type must be jpg,jpeg,png,svg,ico.'),
            'feature_image.mimes'    => __('Feature Image type must be jpg,jpeg,png,svg,webp.'),
            'home_background.mimes'    => __('Background Image type must be jpg,jpeg,png,svg,webp.'),
            'breadcumb_background.mimes'    => __('Background Image type must be jpg,jpeg,png,svg,webp.'),
            'footer_background.mimes'    => __('Background Image type must be jpg,jpeg,png,svg,webp.'),
            'popup_banner.mimes'    => __('Popup Banner must be jpg,jpeg,png,svg,webp.'),
        ];
    }

}
