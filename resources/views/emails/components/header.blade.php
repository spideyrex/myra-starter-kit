{{-- The logo is CID-embedded: remote images are blocked by default in Outlook,
     proxied by Gmail, and the asset disk may be private. --}}
<tr>
    <td style="padding:20px 24px;background:{{ $brand->palette->primaryHex }};color:{{ $brand->palette->foregroundOn($brand->palette->primaryHex) }};">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                @if(!empty($logoBytes))
                    <td style="padding-right:12px;">
                        <img src="{{ $message->embedData($logoBytes, 'brand-logo.png', 'image/png') }}"
                             alt="{{ $brand->name }}" width="40" height="40"
                             style="display:block;border:0;border-radius:6px;">
                    </td>
                @endif
                <td style="font-size:18px;font-weight:700;">{{ $brand->name }}</td>
            </tr>
        </table>
    </td>
</tr>
