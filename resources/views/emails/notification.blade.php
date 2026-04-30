<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 24px 0; }
        .container { max-width: 570px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 24px 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; letter-spacing: 0.5px; }
        .content { padding: 32px; color: #333333; line-height: 1.6; font-size: 15px; }
        .content h2 { margin: 0 0 16px; font-size: 18px; color: #1a1a2e; }
        .btn { display: inline-block; margin: 20px 0; padding: 12px 28px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .footer { padding: 20px 32px; text-align: center; font-size: 12px; color: #999999; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1>{{ __('ui.app_name') }}</h1>
        </div>
        <div class="content">
            <h2>{{ $heading }}</h2>
            <div>{!! nl2br(e($body)) !!}</div>
            @if($actionUrl && $actionText)
                <p style="text-align: center;">
                    <a href="{{ $actionUrl }}" class="btn">{{ $actionText }}</a>
                </p>
            @endif
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ __('ui.app_name') }}. {{ __('ui.all_rights_reserved') }}
        </div>
    </div>
</div>
</body>
</html>
