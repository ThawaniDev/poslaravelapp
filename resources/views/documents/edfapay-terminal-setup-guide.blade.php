<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EdfaPay Terminal Setup Guide — Wameed POS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #f8f9fc;
            line-height: 1.6;
        }

        .page {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            padding: 48px 56px;
        }

        /* ── Header ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1b4f72;
            padding-bottom: 20px;
            margin-bottom: 32px;
        }
        .doc-header .brand { font-size: 20px; font-weight: 700; color: #1b4f72; }
        .doc-header .brand span { color: #f39c12; }
        .doc-header .meta { text-align: right; font-size: 11px; color: #666; line-height: 1.8; }
        .doc-title { font-size: 22px; font-weight: 700; color: #1b4f72; margin-bottom: 4px; }
        .doc-subtitle { font-size: 13px; color: #555; margin-bottom: 28px; }

        /* ── Callout boxes ── */
        .callout {
            border-radius: 6px;
            padding: 12px 16px;
            margin: 16px 0;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .callout-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .callout.info    { background: #ebf5fb; border-left: 4px solid #2e86c1; }
        .callout.warning { background: #fef9e7; border-left: 4px solid #f39c12; }
        .callout.danger  { background: #fdedec; border-left: 4px solid #e74c3c; }
        .callout.success { background: #eafaf1; border-left: 4px solid #27ae60; }
        .callout p { font-size: 12.5px; color: #333; }
        .callout strong { color: #111; }

        /* ── Section headers ── */
        h2 {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            background: #1b4f72;
            padding: 8px 14px;
            border-radius: 4px;
            margin: 32px 0 14px;
        }
        h3 {
            font-size: 13px;
            font-weight: 700;
            color: #1b4f72;
            margin: 20px 0 8px;
            padding-left: 8px;
            border-left: 3px solid #f39c12;
        }

        /* ── Steps ── */
        .steps { counter-reset: step; list-style: none; padding: 0; }
        .steps li {
            counter-increment: step;
            display: flex;
            gap: 14px;
            margin-bottom: 14px;
            align-items: flex-start;
        }
        .steps li::before {
            content: counter(step);
            background: #1b4f72;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .steps li p { font-size: 13px; }
        .steps li p strong { color: #1b4f72; }

        /* ── Letter steps ── */
        .letter-steps { list-style: none; padding: 0; }
        .letter-steps > li {
            display: flex;
            gap: 14px;
            margin-bottom: 18px;
            align-items: flex-start;
        }
        .letter-badge {
            background: #1b4f72;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .letter-badge.green { background: #27ae60; }
        .letter-content { flex: 1; }
        .letter-content strong { display: block; color: #1b4f72; font-size: 13px; margin-bottom: 4px; }
        .letter-content ol { padding-left: 16px; font-size: 12.5px; color: #333; }
        .letter-content ol li { margin-bottom: 4px; }

        /* ── Field table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 12.5px;
        }
        th {
            background: #1b4f72;
            color: #fff;
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
        }
        td { padding: 7px 12px; border-bottom: 1px solid #e8eaf0; vertical-align: top; }
        tr:nth-child(even) td { background: #f6f8fc; }

        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-req  { background: #fde8e8; color: #c0392b; }
        .badge-opt  { background: #eaf4fd; color: #2980b9; }

        code {
            background: #f0f0f0;
            border-radius: 3px;
            padding: 1px 5px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }

        /* ── Checklist ── */
        .checklist { list-style: none; padding: 0; }
        .checklist li {
            padding: 5px 0 5px 24px;
            position: relative;
            font-size: 12.5px;
            border-bottom: 1px dashed #e8eaf0;
        }
        .checklist li::before {
            content: '☐';
            position: absolute;
            left: 0;
            color: #1b4f72;
            font-size: 14px;
        }

        /* ── API endpoints ── */
        .method-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: 700;
        }
        .method-get   { background: #d4efdf; color: #1a7a3f; }
        .method-post  { background: #d6eaf8; color: #1a5276; }
        .method-patch { background: #fde8d8; color: #a04000; }

        .api-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12.5px;
        }
        .api-row:last-child { border-bottom: none; }
        .api-path { font-family: 'Courier New', monospace; font-size: 11px; color: #1a1a2e; flex: 0 0 auto; }

        /* ── Footer ── */
        .doc-footer {
            margin-top: 40px;
            padding-top: 14px;
            border-top: 1px solid #e0e4ef;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #999;
        }

        /* ── Print button ── */
        .print-btn {
            display: inline-block;
            margin-bottom: 24px;
            padding: 9px 20px;
            background: #1b4f72;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .print-btn:hover { background: #154360; }

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .page { padding: 24px 32px; }
            .no-print { display: none; }
            h2 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th  { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .letter-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Header -->
    <div class="doc-header">
        <div>
            <div class="brand">Wameed <span>POS</span></div>
            <div style="font-size:11px;color:#888;margin-top:2px;">Internal Operations Guide</div>
        </div>
        <div class="meta">
            Document: EDFAPAY-SETUP-001<br>
            Version: 1.0<br>
            Date: {{ date('d M Y') }}<br>
            Confidential — Internal Use Only
        </div>
    </div>

    <div class="doc-title">EdfaPay SoftPOS — Terminal Setup Guide</div>
    <div class="doc-subtitle">Step-by-step instructions for Wameed staff to onboard and configure an EdfaPay tap-to-pay terminal in the admin panel.</div>

    <div class="callout info no-print" style="margin-bottom:16px;">
        <div class="callout-icon">ℹ</div>
        <p>This is the printable version of the EdfaPay setup guide. To view the interactive version, visit <strong>/admin/registers/edfapay-setup-guide</strong> in the admin panel.</p>
    </div>

    <!-- Print button -->
    <div class="no-print" style="margin-bottom: 24px;">
        <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p><strong>EdfaPay</strong> is the second supported SoftPOS provider. Unlike NearPay, it uses a single <strong>Terminal Token</strong> from the EdfaPay merchant portal. This token is stored <strong>encrypted at rest</strong> (Laravel encryption) and is never logged in plaintext. The Flutter app uses it to initialize the EdfaPay SDK for tap-to-pay payments.</p>
    </div>

    <div class="callout warning">
        <div class="callout-icon">🔒</div>
        <p><strong>Token Security:</strong> The EdfaPay terminal token is a privileged credential. Treat it like a password — do not paste it in tickets, Slack, or email. Only enter it directly in the admin panel's masked input field.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>1 — Information to Collect Before Setup</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">Collect all required credentials from EdfaPay and the client before opening the admin panel.</p>

    <table>
        <thead>
            <tr><th>Field</th><th>Where to Get It</th><th>Example</th><th>Required?</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>EdfaPay Terminal Token</strong></td>
                <td>EdfaPay merchant portal → Terminals → Generate Token</td>
                <td><code>D08BE4C0FE041A15…</code> (long hex string)</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Acquirer Source</strong></td>
                <td>Client's acquiring bank (HALA, Al Rajhi, SNB, Geidea)</td>
                <td>Geidea</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Mada Merchant Rate</strong></td>
                <td>EdfaPay / acquirer contract</td>
                <td><code>0.006000</code> = 0.6%</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Mada Gateway Rate</strong></td>
                <td>EdfaPay contract (must be ≤ Merchant Rate)</td>
                <td><code>0.004000</code> = 0.4%</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Merchant Fee (SAR)</strong></td>
                <td>EdfaPay / acquirer contract — fixed SAR per transaction</td>
                <td><code>1.000</code> SAR</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Gateway Fee (SAR)</strong></td>
                <td>EdfaPay contract (must be ≤ Merchant Fee)</td>
                <td><code>0.500</code> SAR</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Merchant Rate (%)</strong></td>
                <td>EdfaPay contract — percentage component for mixed model</td>
                <td><code>0.025</code> = 2.5% (or 0 for fixed-only)</td>
                <td><span class="badge badge-opt">Optional</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Gateway Rate (%)</strong></td>
                <td>EdfaPay contract (must be ≤ Merchant Rate %)</td>
                <td><code>0.020</code> = 2.0% (or 0 for fixed-only)</td>
                <td><span class="badge badge-opt">Optional</span></td>
            </tr>
            <tr>
                <td><strong>Device Model &amp; Platform</strong></td>
                <td>Physical device</td>
                <td>Samsung Galaxy / Android 13</td>
                <td><span class="badge badge-opt">Optional</span></td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>2 — How to Obtain the EdfaPay Terminal Token</h2>

    <ul class="steps">
        <li><p>Log in to the <strong>EdfaPay merchant portal</strong> with your EdfaPay account credentials.</p></li>
        <li><p>Navigate to the <strong>Terminals</strong> or <strong>Devices</strong> section.</p></li>
        <li><p>Find or create the terminal entry for the target device.</p></li>
        <li><p>Click <strong>Generate Token</strong> (or "SDK Token" depending on the portal version). A long hex token string is displayed.</p></li>
        <li><p><strong>Copy the token immediately</strong> — it may not be retrievable after the page is closed.</p></li>
        <li><p>Store it securely until you paste it into the admin panel. Do not email or log it.</p></li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>3 — Setup Steps in Admin Panel</h2>

    <ul class="letter-steps">
        <li>
            <div class="letter-badge">A</div>
            <div class="letter-content">
                <strong>Create the register — Basic Info tab</strong>
                <ol>
                    <li>Go to <strong>Terminals</strong> in the sidebar → click <strong>New Terminal</strong>.</li>
                    <li>Select the <strong>Store</strong> this terminal belongs to.</li>
                    <li>Set a <strong>Terminal Name</strong> (e.g. "Cashier 2 — EdfaPay Tap").</li>
                    <li>Enter the <strong>Device ID</strong> (unique identifier — device serial or custom ID).</li>
                    <li>Set <strong>Platform</strong> to <code>android</code> (EdfaPay SDK targets Android).</li>
                    <li>Fill in optional hardware fields (NFC Capable ✓, Device Model, OS Version, Serial).</li>
                </ol>
            </div>
        </li>
        <li>
            <div class="letter-badge">B</div>
            <div class="letter-content">
                <strong>SoftPOS Settings tab — configure EdfaPay</strong>
                <ol>
                    <li>Click the <strong>SoftPOS Settings</strong> tab.</li>
                    <li>Toggle <strong>SoftPOS</strong> ON.</li>
                    <li>Set <strong>SoftPOS Provider</strong> → <strong>EdfaPay</strong>. The <strong>EdfaPay Terminal Token</strong> field appears.</li>
                    <li>Paste the token in the masked <strong>EdfaPay Terminal Token</strong> field. It is stored encrypted automatically on save.</li>
                    <li>Set <strong>SoftPOS Status</strong> to <code>Pending</code> initially.</li>
                    <li>Under <strong>Acquirer Source</strong>: select the acquirer bank.</li>
                </ol>
                <div class="callout warning" style="margin-top: 8px;">
                    <div class="callout-icon">⚠</div>
                    <p>The <strong>Activate SoftPOS</strong> row action checks that a token exists and an acquirer is set. If either is missing, activation returns a 422 error with a descriptive message.</p>
                </div>
            </div>
        </li>
        <li>
            <div class="letter-badge">C</div>
            <div class="letter-content">
                <strong>Fees &amp; Settlement tab — configure bilateral billing rates</strong>
                <ol>
                    <li>Click the <strong>Fees &amp; Settlement</strong> tab.</li>
                    <li><strong>Mada:</strong> Set Mada Merchant Rate (%) and Mada Gateway Rate (%) — gateway ≤ merchant.</li>
                    <li><strong>Visa/MC/Amex — mixed model:</strong> Optionally set Merchant Rate (%) and Gateway Rate (%). Leave both at 0 for fixed-only.</li>
                    <li><strong>Visa/MC/Amex fixed component:</strong> Set Merchant Fee (SAR) and Gateway Fee (SAR). Platform margin = merchant − gateway.</li>
                    <li>Final fee formula: <code>fee = (amount × merchant_rate) + merchant_fee_sar</code></li>
                    <li>Set Settlement Cycle (T+1 / T+2 / T+3 / weekly), Bank Name, and IBAN.</li>
                </ol>
                <div class="callout success" style="margin-top: 8px;">
                    <div class="callout-icon">🧮</div>
                    <p><strong>Mixed fee example (2.5% + 1 SAR):</strong> On a 200 SAR Visa transaction → merchant fee = (200 × 0.025) + 1.000 = <strong>6.000 SAR</strong>. If gateway = 2.0% + 0.500 SAR → gateway = 4.500 SAR. Platform margin = <strong>1.500 SAR</strong>.</p>
                </div>
            </div>
        </li>
        <li>
            <div class="letter-badge">D</div>
            <div class="letter-content">
                <strong>Save the terminal</strong>
                <ol>
                    <li>Click <strong>Save</strong>. The token is encrypted and stored. The <code>edfapay_token_updated_at</code> timestamp is recorded for audit.</li>
                    <li>The terminal appears in the list with SoftPOS status <code>Pending</code>.</li>
                </ol>
            </div>
        </li>
        <li>
            <div class="letter-badge green">E</div>
            <div class="letter-content">
                <strong>Activate SoftPOS</strong>
                <ol>
                    <li>In the Terminals list, click <strong>⋮</strong> → <strong>Activate SoftPOS</strong>.</li>
                    <li>On success, <strong>softpos_status</strong> = Active and <code>softpos_activated_at</code> is recorded.</li>
                    <li>Alternatively via API: <code>POST /api/admin/terminals/{id}/activate-softpos</code> (can supply token in body: <code>{"edfapay_token": "…"}</code>)</li>
                </ol>
            </div>
        </li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>4 — Rotating / Updating the Token</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">If EdfaPay issues a new token (security rotation, device replacement):</p>

    <ul class="steps">
        <li><p>Go to <strong>Terminals</strong> → find the terminal → click <strong>Edit</strong>.</p></li>
        <li><p>Navigate to <strong>SoftPOS Settings</strong> tab → clear the token field and paste the new token.</p></li>
        <li><p>Click <strong>Save</strong>. The system records a new <code>edfapay_token_updated_at</code> timestamp and an audit log entry is created.</p></li>
        <li><p>The Flutter app detects the token change on its next config sync and re-initializes the EdfaPay SDK silently.</p></li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>5 — Flutter App Provisioning</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">The Flutter app receives these fields from <code>GET /api/v1/register/config</code> and initializes the EdfaPay SDK automatically.</p>

    <table>
        <thead>
            <tr><th>API Field</th><th>Value</th><th>Used By App For</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><code>edfapay_token</code></td>
                <td>Decrypted token string</td>
                <td>EdfaPay SDK silent initialization</td>
            </tr>
            <tr>
                <td><code>edfapay_token_updated_at</code></td>
                <td>ISO timestamp</td>
                <td>Detect token rotations → re-initialize SDK</td>
            </tr>
            <tr>
                <td><code>softpos_provider</code></td>
                <td><code>"edfapay"</code></td>
                <td>App selects EdfaPay SDK (not NearPay)</td>
            </tr>
            <tr>
                <td><code>softpos_enabled</code></td>
                <td><code>true</code></td>
                <td>Shows tap-to-pay button in POS checkout screen</td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>6 — Verification Checklist</h2>

    <ul class="checklist">
        <li>Terminal appears in Terminals list with SoftPOS icon ✓</li>
        <li>SoftPOS Provider shows EdfaPay (orange badge) on view page</li>
        <li>SoftPOS Status shows Active (green badge)</li>
        <li>Token status shows key icon ✓ (set) — raw token not displayed after saving</li>
        <li>Token Updated At timestamp is visible on view page</li>
        <li>Acquirer Source is set</li>
        <li>Mada bilateral rates configured (merchant ≥ gateway)</li>
        <li>Visa/MC fixed SAR fees configured</li>
        <li>If mixed model used: % rates set and gateway rate ≤ merchant rate</li>
        <li>Settlement IBAN and bank name are set</li>
        <li>Flutter app launches and shows tap-to-pay button in checkout screen</li>
        <li>A test SoftPOS transaction appears in SoftPOS Transactions with correct fee breakdown</li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>7 — Troubleshooting</h2>

    <table>
        <thead>
            <tr><th>Symptom</th><th>Likely Cause</th><th>Fix</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>"EdfaPay terminal token is not set" on activation</td>
                <td>Token field empty or not saved</td>
                <td>Edit terminal → SoftPOS Settings → paste token → Save</td>
            </tr>
            <tr>
                <td>Activate SoftPOS button not visible</td>
                <td>SoftPOS not enabled, status Active, or acquirer missing</td>
                <td>Enable toggle + set acquirer. Button appears when status ≠ Active and token exists.</td>
            </tr>
            <tr>
                <td>EdfaPay Token field not appearing in form</td>
                <td>Provider not set to EdfaPay</td>
                <td>Set <strong>SoftPOS Provider</strong> = EdfaPay (field is conditionally shown)</td>
            </tr>
            <tr>
                <td>SDK fails — app shows error on init</td>
                <td>Token invalid or expired in EdfaPay's system</td>
                <td>Regenerate token in EdfaPay portal → update terminal in admin</td>
            </tr>
            <tr>
                <td>Mixed fee gives wrong platform margin</td>
                <td>Gateway rate or fee &gt; merchant</td>
                <td>Ensure merchant rate ≥ gateway rate and merchant fee ≥ gateway fee for both SAR and %</td>
            </tr>
            <tr>
                <td>Token shows "not set" even after saving</td>
                <td>APP_KEY changed — encryption mismatch</td>
                <td>Re-enter the token. Check audit log for the save event.</td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>8 — API Quick Reference</h2>

    <div class="api-row">
        <span class="method-badge method-post">POST</span>
        <span class="api-path">/api/admin/terminals/{id}/activate-softpos</span>
        <span style="color:#555;">Activate; optionally supply edfapay_token in body</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-post">POST</span>
        <span class="api-path">/api/admin/terminals/{id}/suspend-softpos</span>
        <span style="color:#555;">Suspend SoftPOS</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-post">POST</span>
        <span class="api-path">/api/admin/terminals/{id}/deactivate-softpos</span>
        <span style="color:#555;">Deactivate SoftPOS</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-patch">PATCH</span>
        <span class="api-path">/api/admin/terminals/{id}/softpos-billing</span>
        <span style="color:#555;">Update bilateral billing rates (all 6 rate fields)</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-patch">PATCH</span>
        <span class="api-path">/api/admin/terminals/{id}</span>
        <span style="color:#555;">Update terminal including edfapay_token (set to null to clear)</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-get">GET</span>
        <span class="api-path">/api/admin/terminals/{id}</span>
        <span style="color:#555;">View: edfapay_token (decrypted), token_updated_at, softpos_provider</span>
    </div>

    <!-- Print button (bottom) -->
    <div class="no-print" style="margin-top: 32px;">
        <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <span>EDFAPAY-SETUP-001</span>
        <span>Confidential. Not for distribution outside Wameed operations team.</span>
        <span>© {{ date('Y') }} Wameed</span>
    </div>

</div>
</body>
</html>
