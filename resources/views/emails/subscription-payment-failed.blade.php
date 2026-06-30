<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Naplata nije uspjela – Zalet</title>
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

          {{-- Card --}}
          <tr>
            <td style="background-color:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:40px 36px;">

              {{-- Title --}}
              <p style="margin:0 0 8px;color:#fff;font-size:24px;font-weight:700;text-align:center;">
                Naplata nije uspjela
              </p>
              <p style="margin:0 0 28px;color:#888;font-size:15px;text-align:center;line-height:1.6;">
                Nismo uspjeli naplatiti tvoju {{ $planName }} pretplatu. Sljedeći pokušaj je {{ $nextAttempt }} (ostalo pokušaja: {{ $attemptsLeft }}).
              </p>

              @if ($error)
              <p style="margin:0 0 16px;color:#555;font-size:12px;text-align:center;background-color:#0f0f0f;padding:12px;border-radius:8px;border-left:3px solid #e63946;">
                Greška: {{ $error }}
              </p>
              @endif

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
