<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    <title>سامانه هوشمند مدیریت اسناد</title>

    @routes('user')
    @vite('resources/vue/app.js')
    @inertiaHead

    <link rel="shortcut icon" href="{{ asset('fav-icon.png') }}">
    <meta name="description" content="سامانه هوشمند مدیریت اسناد">
    <meta name="keywords" content="سامانه هوشمند مدیریت اسناد">
</head>
<body dir="rtl">
@inertia
</body>
</html>
