@php($bg = $brand->palette->primaryHex)
@php($fg = $brand->palette->foregroundOn($bg))
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0;">
    <tr>
        <td style="border-radius:6px;background:{{ $bg }};">
            <a href="{{ $url }}" style="display:inline-block;padding:10px 18px;font-size:14px;font-weight:600;color:{{ $fg }};text-decoration:none;">{{ $label }}</a>
        </td>
    </tr>
</table>
