<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine ?? $brand->name }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:{{ $brand->typography->stack('sans') }};color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                    @include('emails.components.header')

                    <tr>
                        <td style="padding:24px;font-size:15px;line-height:1.6;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>

                    @include('emails.components.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
