<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Potvrdi email – Zalet</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f0f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f0f;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;">

          {{-- Logo / Header --}}
          <tr>
            <td align="center" style="padding-bottom:32px;">
              <table cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:linear-gradient(135deg,#e63946,#c1121f);border-radius:16px;width:56px;height:56px;text-align:center;vertical-align:middle;">
                    <span style="color:#fff;font-size:28px;font-weight:900;line-height:56px;display:block;">Z</span>
                  </td>
                </tr>
              </table>
              <p style="margin:12px 0 0;color:#fff;font-size:22px;font-weight:800;letter-spacing:0.15em;">ZALET</p>
              <p style="margin:4px 0 0;color:#666;font-size:12px;letter-spacing:0.08em;">ZAJETNICA DIJASPORE</p>
            </td>
          </tr>

          {{-- Card --}}
          <tr>
            <td style="background-color:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:40px 36px;">

              {{-- Title --}}
              <p style="margin:0 0 8px;color:#fff;font-size:24px;font-weight:700;text-align:center;">
                Dobrodošao/la na Zalet! 🎉
              </p>
              <p style="margin:0 0 28px;color:#888;font-size:15px;text-align:center;line-height:1.6;">
                Jedan korak do kraja — potvrdi svoju email adresu kako bismo aktivirali tvoj nalog.
              </p>

              {{-- CTA Button --}}
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding-bottom:28px;">
                    <a href="{{ $verificationUrl }}"
                       style="display:inline-block;background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:50px;letter-spacing:0.03em;">
                      Potvrdi email adresu
                    </a>
                  </td>
                </tr>
              </table>

              {{-- Divider --}}
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="border-top:1px solid #2a2a2a;padding-top:24px;">
                    <p style="margin:0 0 8px;color:#666;font-size:12px;text-align:center;">
                      Dugme ne radi? Kopiraj ovaj link u browser:
                    </p>
                    <p style="margin:0;word-break:break-all;text-align:center;">
                      <a href="{{ $verificationUrl }}" style="color:#e63946;font-size:11px;text-decoration:none;">
                        {{ $verificationUrl }}
                      </a>
                    </p>
                  </td>
                </tr>
              </table>

              {{-- Expiry note --}}
              <p style="margin:24px 0 0;color:#555;font-size:12px;text-align:center;line-height:1.6;">
                Link važi <strong style="color:#888;">60 minuta</strong>. Ako nisi kreirao/la nalog, ignorišite ovaj email.
              </p>
            </td>
          </tr>

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
