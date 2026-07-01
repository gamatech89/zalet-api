<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kupovina kovanica – Zalet</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f0f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f0f;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;">

          {{-- Logo / Header --}}
          <tr>
            <td align="center" style="padding-bottom:32px;">
              <img src="https://zaletyu.com/icons/icon-192.png" alt="Zalet" width="64" height="64" style="display:block;margin:0 auto;border-radius:16px;" />
              <p style="margin:12px 0 0;color:#fff;font-size:22px;font-weight:800;letter-spacing:0.15em;">ZALET</p>
              <p style="margin:4px 0 0;color:#666;font-size:12px;letter-spacing:0.08em;">POSTANI DEO EKIPE</p>
            </td>
          </tr>

          {{-- Confirmation Card --}}
          <tr>
            <td style="background-color:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:40px 36px;">

              <p style="margin:0 0 4px;color:#f59e0b;font-size:13px;font-weight:600;text-align:center;letter-spacing:0.06em;">KUPOVINA USPEŠNA</p>
              <p style="margin:0 0 28px;color:#fff;font-size:24px;font-weight:700;text-align:center;">
                {{ number_format($coins) }} ZaletCoins
              </p>
              <p style="margin:0 0 28px;color:#888;font-size:15px;text-align:center;line-height:1.6;">
                Kovanice su dodate na tvoj nalog. Koristi ih za poklone, sadržaj i još mnogo toga!
              </p>

              {{-- Details --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #2a2a2a;border-radius:12px;overflow:hidden;margin-bottom:32px;">
                <tr>
                  <td style="padding:12px 16px;border-bottom:1px solid #222;">
                    <span style="color:#666;font-size:12px;">Kupljeno kovanica</span><br />
                    <span style="color:#f59e0b;font-size:18px;font-weight:700;">{{ number_format($coins) }} ZLC</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;border-bottom:1px solid #222;">
                    <span style="color:#666;font-size:12px;">Plaćeno</span><br />
                    <span style="color:#fff;font-size:14px;font-weight:700;">{{ $amountRsd }} RSD</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 16px;">
                    <span style="color:#666;font-size:12px;">Datum</span><br />
                    <span style="color:#fff;font-size:14px;">{{ $date }}</span>
                  </td>
                </tr>
              </table>

              {{-- CTA --}}
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <a href="https://zaletyu.com/wallet"
                       style="display:inline-block;background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:50px;letter-spacing:0.03em;">
                      Pogledaj stanje
                    </a>
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          {{-- Receipt --}}
          @if($orderId)
          <tr>
            <td style="padding-top:24px;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#111;border:1px solid #222;border-radius:16px;padding:28px 32px;">
                <tr>
                  <td>
                    <p style="margin:0 0 16px;color:#555;font-size:11px;font-weight:600;letter-spacing:0.1em;">POTVRDA PLAĆANJA</p>
                    <table width="100%" cellpadding="0" cellspacing="4">
                      <tr>
                        <td style="color:#666;font-size:12px;padding:4px 0;">Broj narudžbine</td>
                        <td style="color:#999;font-size:12px;text-align:right;font-family:monospace;">{{ $orderId }}</td>
                      </tr>
                      <tr>
                        <td style="color:#666;font-size:12px;padding:4px 0;">Datum</td>
                        <td style="color:#999;font-size:12px;text-align:right;">{{ $date }}</td>
                      </tr>
                      <tr>
                        <td style="color:#666;font-size:12px;padding:4px 0;">Usluga</td>
                        <td style="color:#999;font-size:12px;text-align:right;">ZaletCoin kupovina</td>
                      </tr>
                      <tr>
                        <td colspan="2" style="border-top:1px solid #222;padding-top:8px;margin-top:8px;"></td>
                      </tr>
                      <tr>
                        <td style="color:#888;font-size:13px;font-weight:600;padding:4px 0;">Ukupno</td>
                        <td style="color:#fff;font-size:13px;font-weight:700;text-align:right;">{{ $amountRsd }} RSD</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          @endif

          {{-- Footer --}}
          <tr>
            <td style="padding-top:24px;text-align:center;">
              <p style="margin:0;color:#444;font-size:12px;line-height:1.8;">
                © {{ date('Y') }} Zalet · Zajetnica dijaspore<br />
                <a href="https://zaletyu.com" style="color:#666;text-decoration:none;">zaletyu.com</a>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
