<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $announcementTitle }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 24px 0; }
        .container { max-width: 570px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 24px 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-maintenance { background: #fee2e2; color: #991b1b; }
        .badge-update { background: #d1fae5; color: #065f46; }
        .badge-feature { background: #e0e7ff; color: #3730a3; }
        .content { padding: 32px; color: #333333; line-height: 1.6; font-size: 15px; }
        .content h2 { margin: 0 0 16px; font-size: 18px; color: #1a1a2e; }
        .footer { padding: 20px 32px; text-align: center; font-size: 12px; color: #999999; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1>Wameed POS</h1>
        </div>
        <div class="content">
            <span class="badge badge-{{ $type }}">{{ ucfirst($type) }}</span>
            <h2>{{ $announcementTitle }}</h2>
            <div>{!! $announcementBody !!}</div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Wameed POS. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
