<!DOCTYPE html>
<html lang="{{ Language::code() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title or 'Page' }} - {{ Config::get('app.name') }}</title>

@php

echo Asset::build('css', array(
    vendor_url('dist/css/bootstrap.min.css', 'twbs/bootstrap'),
    vendor_url('dist/css/bootstrap-theme.min.css', 'twbs/bootstrap'),
    'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
    asset_url('css/bootstrap-xl-mod.min.css'),
    asset_url('css/style.css'),
));

echo Asset::render('css', 'header');

echo Asset::build('js', array(
        asset_url('js/sprintf.min.js'),
        'https://code.jquery.com/jquery-1.12.4.min.js',
));

echo Asset::render('js', 'header');


</head>
<body>

@section('header')

@show

<div class="container">

@section('content')
    {{ $content }}
@show

</div>

@section('footer')

@show

@php

echo Asset::build('js', array(
    vendor_url('dist/js/bootstrap.min.js', 'twbs/bootstrap'),
));

echo Asset::render('js', 'footer');

@endphp

<!-- DO NOT DELETE! - Profiler -->

</body>
</html>
