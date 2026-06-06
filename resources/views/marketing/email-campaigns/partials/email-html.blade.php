{{--
    Email HTML template — mirrors the MailerLite Zapier template.
    Variables: $subjectLine, $preheader, $headlineTop, $paragraphTop1, $paragraphTop2,
               $couponCode, $couponExpiry, $couponFinePrint, $url1,
               $headlineBottom, $paragraphBottom, $imageUrl, $preview (bool)
    Notes:
      - {$url}, {$name|default('...')}, {$unsubscribe} are MailerLite merge tags — output
        literally in live emails, replaced with preview stand-ins when $preview=true.
      - Paragraph fields allow HTML (admin-entered, trusted).
--}}
<style type="text/css">
  div[style*="margin: 16px 0;"] { margin:0 !important; }
  a[x-apple-data-detectors] { color:inherit !important; text-decoration:none !important; }
  .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; max-height:0; overflow:hidden; mso-hide:all; }
  .row { padding:0 20px; }
  @media screen and (min-width:640px) {
    .row { padding:0 50px !important; }
  }
  @media screen and (max-width:640px) {
    .h1    { font-size:26px !important; line-height:130% !important; }
    .h2    { font-size:22px !important; line-height:130% !important; }
    .coupon{ font-size:22px !important; word-break:break-all !important; }
  }
</style>

<div class="preheader">{{ $preheader }}</div>

<center role="presentation" style="width:100%;">
  <!--[if mso]>
  <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" width="640">
  <tr><td>
  <![endif]-->
  <div style="max-width:640px; margin:0 auto;">

    <!-- VIEW IN BROWSER -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr><td height="20">&nbsp;</td></tr>
      <tr>
        <td class="row" align="right" style="font-family:'Inter',Arial,sans-serif; color:#111; font-size:12px; line-height:18px;">
          @if($preview)
            <a href="#" style="color:#111; text-decoration:underline;">View in browser</a>
          @else
            <a href="{$url}" style="color:#111; text-decoration:underline;">View in browser</a>
          @endif
        </td>
      </tr>
      <tr><td height="20">&nbsp;</td></tr>
    </table>

    <!-- MAIN CARD -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="border:1px solid #EAECED; border-radius:8px; overflow:hidden; border-collapse:separate;">
      <tr>
        <td>

          <!-- LOGO -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#2B4158">
            <tr><td height="10">&nbsp;</td></tr>
            <tr>
              <td class="row" align="center">
                <a href="https://screenplayreaders.com" target="_self" style="text-decoration:none;">
                  <img src="https://storage.mlcdn.com/account_image/557150/UyrCaevm57GfY82HYxlZejYBCfShfyyAcapl8CdU.png"
                       width="503" alt="Screenplay Readers"
                       style="max-width:503px; width:100%; height:auto; display:block;">
                </a>
              </td>
            </tr>
            <tr><td height="10">&nbsp;</td></tr>
          </table>

          <!-- HEADLINE TOP -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr><td height="20">&nbsp;</td></tr>
            <tr>
              <td class="row">
                <h1 class="h1" style="font-family:'Aleo',Georgia,serif; color:#2C435B; font-size:36px; line-height:125%; font-weight:bold; margin:0; text-align:center;">
                  {{ $headlineTop }}
                </h1>
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>

          <!-- PARAGRAPHS TOP -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row">
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:16px; line-height:165%; margin:0 0 10px 0;">
                  @if($preview)
                    Hi Screenwriter -
                  @else
                    Hi {$name|default('Screenwriter')} -
                  @endif
                </p>
                @if($paragraphTop1)
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:16px; line-height:165%; margin:0 0 10px 0; text-align:justify;">
                  {!! $paragraphTop1 !!}
                </p>
                @endif
                @if($paragraphTop2)
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:16px; line-height:165%; margin:0; text-align:justify;">
                  {!! $paragraphTop2 !!}
                </p>
                @endif
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>

          @if($imageUrl)
          <!-- PROMOTIONAL IMAGE -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr><td height="20">&nbsp;</td></tr>
            <tr>
              <td class="row" align="center">
                <img src="{{ $imageUrl }}" width="560" alt=""
                     style="max-width:560px; width:100%; height:auto; display:block;">
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>
          @endif

          <!-- COUPON CODE -->
          @if($couponCode)
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row" style="text-align:center;">
                <h2 class="coupon h2" style="font-family:'Space Mono',ui-monospace,Menlo,monospace; color:#a52834; font-size:36px; line-height:125%; font-weight:bold; margin:0; text-align:center; word-break:break-all;">
                  {{ $couponCode }}
                </h2>
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>
          @endif

          <!-- CTA BUTTON -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row" align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="max-width:420px; margin:0 auto; width:100%;">
                  <tr>
                    <td align="center" valign="middle">
                      <!--[if mso]>
                      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                        href="{{ $url1 }}"
                        style="height:44px;v-text-anchor:middle;width:420px;" arcsize="12%" stroke="f">
                        <v:fill color="#5E97D8"/>
                        <w:anchorlock/>
                        <center style="color:#fff;font-family:sans-serif;font-size:14px;"><u>Click here</u> to order</center>
                      </v:roundrect>
                      <![endif]-->
                      <!--[if !mso]><!-->
                      <a href="{{ $url1 }}" target="_blank"
                         style="display:block; background:#5E97D8; border-radius:6px; padding:14px 18px;
                                font-family:'Aleo',Georgia,serif; color:#ffffff; font-size:14px;
                                letter-spacing:0.025em; text-decoration:none; line-height:16px;
                                text-align:center; width:100%; box-sizing:border-box;">
                        <u>Click here</u> to order
                      </a>
                      <!--<![endif]-->
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>

          @if($couponFinePrint)
          <!-- FINE PRINT -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row">
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:11px; line-height:165%; margin:0; text-align:center;">
                  {{ $couponFinePrint }}
                </p>
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>
          @endif

          <!-- HEADLINE BOTTOM -->
          @if($headlineBottom)
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row">
                <h2 class="h2" style="font-family:'Aleo',Georgia,serif; color:#2C435B; font-size:36px; line-height:125%; font-weight:bold; margin:0; text-align:center;">
                  {{ $headlineBottom }}
                </h2>
              </td>
            </tr>
            @if($paragraphBottom)
            <tr><td height="12">&nbsp;</td></tr>
            <tr>
              <td class="row">
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:16px; line-height:165%; margin:0; text-align:justify;">
                  {!! $paragraphBottom !!}
                </p>
              </td>
            </tr>
            @endif
            <tr><td height="20">&nbsp;</td></tr>
          </table>
          @endif

          <!-- SIGNATURE IMAGES -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr>
              <td class="row" align="center">
                <img src="https://storage.mlcdn.com/account_image/557150/eJGLvcUJvr0xpMMe7qSupYT5U3I2U0GbnEviUrAk.png"
                     width="61" alt="" style="display:block; margin:0 auto;">
              </td>
            </tr>
            <tr><td height="18">&nbsp;</td></tr>
            <tr>
              <td class="row" align="center">
                <img src="https://storage.mlcdn.com/account_image/557150/IfIiw2FrOn6CigRloEDszxl3LpDtp2rKeCmVqmQc.png"
                     width="66" alt="" style="display:block; margin:0 auto;">
              </td>
            </tr>
          </table>

          <!-- BIO -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F4E6">
            <tr><td height="6">&nbsp;</td></tr>
            <tr>
              <td class="row">
                <p style="font-family:'Aleo',Georgia,serif; color:#515856; font-size:12px; line-height:165%; margin:0; text-align:center;">
                  Author, Screenwriter, Script Consultant, Literary Manager,<br>
                  Founder of Screenplay Readers
                </p>
              </td>
            </tr>
            <tr><td height="20">&nbsp;</td></tr>
          </table>

          <!-- FOOTER -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#2B4158">
            <tr><td height="40">&nbsp;</td></tr>
            <tr>
              <td class="row">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td valign="top" style="padding-bottom:12px;">
                      <h5 style="font-family:'Aleo',Georgia,serif; color:#fff; font-size:10px; font-weight:normal; margin:0 0 6px 0;">Screenplay Readers</h5>
                      <p style="font-family:'Aleo',Georgia,serif; color:#fff; font-size:10px; line-height:150%; margin:0;">
                        254 N Lake Ave, Pasadena<br>United States of America
                      </p>
                    </td>
                  </tr>
                  <tr>
                    <td valign="top">
                      <p style="font-family:'Aleo',Georgia,serif; color:#fff; font-size:10px; line-height:150%; margin:0 0 6px 0;">
                        You received this email because you manually signed up on our website or purchased a service from us.
                      </p>
                      <p style="font-family:'Aleo',Georgia,serif; color:#fff; font-size:10px; margin:0;">
                        @if($preview)
                          <a href="#" style="color:#fff; text-decoration:underline;">Unsubscribe</a>
                        @else
                          <a href="{$unsubscribe}" style="color:#fff; text-decoration:underline;">Unsubscribe</a>
                        @endif
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr><td height="40">&nbsp;</td></tr>
          </table>

        </td>
      </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr><td height="20">&nbsp;</td></tr>
    </table>

  </div>
  <!--[if mso]></td></tr></table><![endif]-->
</center>
