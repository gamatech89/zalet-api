<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Poklon za tebe – Zalet</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f0f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f0f;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;">

          {{-- Logo --}}
          <tr>
            <td align="center" style="padding-bottom:32px;">
              <img src="https://zaletyu.com/icons/icon-192.png" alt="Zalet" width="64" height="64" style="display:block;margin:0 auto;border-radius:16px;" />
              <p style="margin:12px 0 0;color:#fff;font-size:22px;font-weight:800;letter-spacing:0.15em;">ZALET</p>
              <p style="margin:4px 0 0;color:#666;font-size:12px;letter-spacing:0.08em;">POSTANI DEO EKIPE</p>
            </td>
          </tr>

          {{-- Main Card --}}
          <tr>
            <td style="background-color:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:40px 36px;">

              <p style="margin:0 0 4px;color:#f59e0b;font-size:13px;font-weight:600;text-align:center;letter-spacing:0.06em;">POKLON ZA TEBE</p>
              <p style="margin:0 0 20px;color:#fff;font-size:24px;font-weight:700;text-align:center;">
                Hej @{{ $username }}, hvala na poverenju!
              </p>

              <p style="margin:0 0 24px;color:#aaa;font-size:15px;line-height:1.7;text-align:center;">
                Odlučili smo da snizimo cenu Premium pretplate sa <strong style="color:#fff;">2.000 RSD</strong> na
                <strong style="color:#4ade80;">750 RSD</strong> mesečno — kako bi Zalet bio dostupan što većem broju diaspora zajednice.
              </p>

              <p style="margin:0 0 28px;color:#aaa;font-size:15px;line-height:1.7;text-align:center;">
                Pošto si ti uzeo pretplatu po staroj ceni, u znak zahvalnosti i kao izvinjenje
                kreditujemo ti <strong style="color:#f59e0b;">{{ number_format($coins) }} ZaletCoina</strong> direktno na tvoj novčanik.
              </p>

              {{-- Coin highlight box --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
                <tr>
                  <td align="center" style="background:linear-gradient(135deg,#1c1200,#2a1c00);border:1px solid #f59e0b33;border-radius:14px;padding:24px;">
                    <p style="margin:0 0 6px;color:#f59e0b;font-size:13px;font-weight:600;letter-spacing:0.08em;">UPRAVO DODATO NA TVOJ NALOG</p>
                    <p style="margin:0;color:#fff;font-size:36px;font-weight:800;">{{ number_format($coins) }} ZC</p>
                    <p style="margin:6px 0 0;color:#888;font-size:13px;">ZaletCoin · odmah dostupno</p>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 28px;color:#666;font-size:14px;line-height:1.7;text-align:center;">
                Coini su već na tvom novčaniku — možeš ih koristiti za poklone, PPV sadržaj,
                ili ih poslati kome hoćeš.
              </p>

              {{-- CTA --}}
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <a href="https://zaletyu.com/wallet"
                       style="display:inline-block;background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:50px;letter-spacing:0.03em;">
                      Pogledaj novčanik
                    </a>
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td style="padding-top:24px;text-align:center;">
              <p style="margin:0;color:#444;font-size:12px;line-height:1.8;">
                Tvoja pretplata ostaje aktivna do isteka tekućeg perioda.<br />
                Pitanja? Piši nam na <a href="mailto:{{ $company['email'] }}" style="color:#666;text-decoration:none;">{{ $company['email'] }}</a>
              </p>
              <p style="margin:12px 0 0;color:#333;font-size:12px;line-height:1.8;">
                © {{ date('Y') }} {{ $company['name'] }} · <a href="https://zaletyu.com" style="color:#444;text-decoration:none;">zaletyu.com</a>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
