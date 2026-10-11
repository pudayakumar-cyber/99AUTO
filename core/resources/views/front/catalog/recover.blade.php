<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Restore your cart | 99 Auto Parts</title>
    <style>
        body{margin:0;background:#f4f5f6;color:#20242a;font:17px/1.6 system-ui,sans-serif}
        main{max-width:520px;margin:10vh auto;padding:32px;background:white;border-top:5px solid #b92126;border-radius:8px}
        h1{font-size:28px;line-height:1.2}button{background:#b92126;color:white;border:0;border-radius:4px;padding:14px 20px;font:inherit;cursor:pointer}
        a{color:#922025}button:focus-visible,a:focus-visible{outline:3px solid #20242a;outline-offset:4px}
        @media(max-width:600px){main{margin:32px 16px;padding:24px}}
    </style>
</head>
<body><main>
    <p><strong>99 AUTO PARTS</strong></p>
    @if($available)
        <h1>Ready to finish your order?</h1>
        <p>Restore your saved items to this browser. This replaces your current cart. We will check current prices and availability before you review your order.</p>
        <form method="post" action="{{ $action }}">
            @csrf
            <button type="submit">Restore my cart</button>
        </form>
    @else
        <h1>This cart link has expired</h1>
        <p>Your current cart has not changed. You can browse our catalog to build a new order.</p>
    @endif
    <p><a href="{{ $shopUrl }}">Continue shopping</a></p>
</main></body></html>
