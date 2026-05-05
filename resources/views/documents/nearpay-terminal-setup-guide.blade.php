<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NearPay Terminal Setup Guide — Wameed POS</title>
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

        /* ── Letter steps (A, B, C ...) ── */
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
        .endpoint {
            background: #1a1a2e;
            color: #a9d0f5;
            border-radius: 4px;
            padding: 3px 8px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            word-break: break-all;
        }
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
            Document: NEARPAY-SETUP-001<br>
            Version: 1.0<br>
            Date: {{ date('d M Y') }}<br>
            Confidential — Internal Use Only
        </div>
    </div>

    <div class="doc-title">NearPay SoftPOS — Terminal Setup Guide</div>
    <div class="doc-subtitle">Step-by-step instructions for Wameed staff to onboard and configure a NearPay tap-to-pay terminal in the admin panel.</div>

    <div class="callout info no-print" style="margin-bottom:16px;">
        <div class="callout-icon">ℹ</div>
        <p>This is the printable version of the NearPay setup guide. To view the interactive version, visit <strong>/admin/registers/nearpay-setup-guide</strong> in the admin panel.</p>
    </div>

    <!-- Print button -->
    <div class="no-print" style="margin-bottom: 24px;">
        <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p><strong>NearPay</strong> is the default SoftPOS provider. It requires a <strong>Terminal ID (TID)</strong> and <strong>Merchant ID (MID)</strong> issued by NearPay during device onboarding. The Flutter app uses these to initialize the NearPay SDK for tap-to-pay payments.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>1 — Information to Collect Before Setup</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">Collect all required credentials from NearPay and the client before opening the admin panel.</p>

    <table>
        <thead>
            <tr><th>Field</th><th>Where to Get It</th><th>Example</th><th>Required?</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>NearPay Terminal ID (TID)</strong></td>
                <td>NearPay merchant portal → My Terminals</td>
                <td><code>TID-ABC123</code></td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Merchant ID (MID)</strong></td>
                <td>NearPay merchant portal → Account Settings</td>
                <td><code>MID-999001</code></td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Acquirer Source</strong></td>
                <td>Client's acquiring bank (HALA, Al Rajhi, SNB, Geidea)</td>
                <td>HALA</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Mada Merchant Rate</strong></td>
                <td>Contract with acquirer</td>
                <td><code>0.006000</code> = 0.6%</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Mada Gateway Rate</strong></td>
                <td>NearPay contract (must be ≤ Merchant Rate)</td>
                <td><code>0.004000</code> = 0.4%</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Merchant Fee (SAR)</strong></td>
                <td>Contract with acquirer</td>
                <td><code>1.000</code> SAR</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Visa/MC Gateway Fee (SAR)</strong></td>
                <td>NearPay contract (must be ≤ Merchant Fee)</td>
                <td><code>0.500</code> SAR</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Device Model &amp; OS</strong></td>
                <td>Physical device</td>
                <td>Samsung Galaxy / Android 13</td>
                <td><span class="badge badge-opt">Optional</span></td>
            </tr>
            <tr>
                <td><strong>Settlement IBAN</strong></td>
                <td>Merchant's bank account</td>
                <td>SA1234…</td>
                <td><span class="badge badge-opt">Optional</span></td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>2 — Setup Steps in Admin Panel</h2>

    <ul class="letter-steps">
        <li>
            <div class="letter-badge">A</div>
            <div class="letter-content">
                <strong>Create the register — Basic Info tab</strong>
                <ol>
                    <li>Go to <strong>Terminals</strong> in the sidebar → click <strong>New Terminal</strong>.</li>
                    <li>Select the <strong>Store</strong> this terminal belongs to.</li>
                    <li>Set a <strong>Terminal Name</strong> (e.g. "Cashier 1 — Tap to Pay").</li>
                    <li>Enter the <strong>Device ID</strong> (unique identifier — device serial or custom ID).</li>
                    <li>Set <strong>Platform</strong> to <code>android</code> (NearPay SDK targets Android).</li>
                    <li>Fill in optional hardware fields (Device Model, OS Version, Serial Number, NFC Capable ✓).</li>
                </ol>
            </div>
        </li>
        <li>
            <div class="letter-badge">B</div>
            <div class="letter-content">
                <strong>SoftPOS Settings tab — configure NearPay</strong>
                <ol>
                    <li>Click the <strong>SoftPOS Settings</strong> tab.</li>
                    <li>Toggle <strong>SoftPOS</strong> ON — additional fields appear.</li>
                    <li>Set <strong>SoftPOS Provider</strong> → <strong>NearPay</strong>. The TID and MID fields appear.</li>
                    <li>Set <strong>SoftPOS Status</strong> to <code>Pending</code> initially.</li>
                    <li>Enter the <strong>NearPay Terminal ID (TID)</strong> from the NearPay portal.</li>
                    <li>Enter the <strong>Merchant ID (MID)</strong> from the NearPay portal.</li>
                    <li>Under <strong>Acquirer Source</strong>: select the acquirer bank.</li>
                </ol>
                <div class="callout warning" style="margin-top: 8px;">
                    <div class="callout-icon">⚠</div>
                    <p>The <strong>Activate SoftPOS</strong> row action is blocked until TID and Acquirer Source are both set.</p>
                </div>
            </div>
        </li>
        <li>
            <div class="letter-badge">C</div>
            <div class="letter-content">
                <strong>Fees &amp; Settlement tab — configure bilateral billing rates</strong>
                <ol>
                    <li>Click the <strong>Fees &amp; Settlement</strong> tab. The <strong>SoftPOS Bilateral Billing</strong> section is visible only when SoftPOS is enabled.</li>
                    <li><strong>Mada:</strong> Set Mada Merchant Rate and Mada Gateway Rate (gateway ≤ merchant).</li>
                    <li><strong>Visa/MC/Amex fixed fee:</strong> Set Merchant Fee (SAR) and Gateway Fee (SAR).</li>
                    <li><strong>Visa/MC/Amex mixed model (optional):</strong> Set Merchant Rate (%) and Gateway Rate (%) for a combined % + fixed SAR fee. Leave both at 0 for fixed-only.</li>
                    <li>Set Settlement Cycle (T+1 / T+2 / T+3 / weekly), Bank Name, and IBAN.</li>
                </ol>
            </div>
        </li>
        <li>
            <div class="letter-badge">D</div>
            <div class="letter-content">
                <strong>Save the terminal</strong>
                <ol>
                    <li>Click <strong>Save</strong>. All three tabs are saved together.</li>
                    <li>The terminal appears in the list with SoftPOS status <code>Pending</code>.</li>
                </ol>
            </div>
        </li>
        <li>
            <div class="letter-badge green">E</div>
            <div class="letter-content">
                <strong>Activate SoftPOS</strong>
                <ol>
                    <li>In the Terminals list, click the <strong>⋮</strong> actions menu → <strong>Activate SoftPOS</strong>.</li>
                    <li>Confirm the dialog. Status changes to <strong>Active</strong>.</li>
                    <li>Alternatively via API: <code>POST /api/admin/terminals/{id}/activate-softpos</code></li>
                </ol>
            </div>
        </li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>3 — Flutter App Provisioning</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">After admin setup, the Flutter POS app auto-provisions the NearPay SDK on first launch using these fields.</p>

    <table>
        <thead>
            <tr><th>API Field</th><th>Value</th><th>Used By App For</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><code>nearpay_tid</code></td>
                <td>TID from NearPay portal</td>
                <td>NearPay SDK initialization</td>
            </tr>
            <tr>
                <td><code>nearpay_mid</code></td>
                <td>MID from NearPay portal</td>
                <td>Identifies the merchant account on NearPay</td>
            </tr>
            <tr>
                <td><code>softpos_provider</code></td>
                <td><code>"nearpay"</code></td>
                <td>App selects NearPay SDK (not EdfaPay)</td>
            </tr>
            <tr>
                <td><code>softpos_enabled</code></td>
                <td><code>true</code></td>
                <td>Shows tap-to-pay button in POS checkout screen</td>
            </tr>
        </tbody>
    </table>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p>The app fetches register config via <code>GET /api/v1/register/config</code>. It checks <code>softpos_provider === "nearpay"</code> and loads the NearPay SDK using TID + MID. No manual device configuration is needed.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>4 — Verification Checklist</h2>

    <ul class="checklist">
        <li>Terminal appears in Terminals list with SoftPOS icon ✓</li>
        <li>SoftPOS Status shows Active (green badge)</li>
        <li>SoftPOS Provider shows NearPay (blue badge) on the view page</li>
        <li>NearPay TID and MID are visible on the terminal view page</li>
        <li>Acquirer Source is set</li>
        <li>Bilateral billing rates configured (Mada % + Visa/MC fixed SAR fees)</li>
        <li>Settlement IBAN and bank name are set</li>
        <li>Flutter app launches and shows the tap-to-pay button on checkout</li>
        <li>A test SoftPOS transaction appears in SoftPOS Transactions list after a test tap</li>
        <li>Transaction fee breakdown is correctly applied (Mada vs Visa/MC)</li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>5 — Troubleshooting</h2>

    <table>
        <thead>
            <tr><th>Symptom</th><th>Likely Cause</th><th>Fix</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Activate SoftPOS button not visible</td>
                <td>TID or acquirer source not set</td>
                <td>Edit terminal → SoftPOS Settings → fill TID + acquirer</td>
            </tr>
            <tr>
                <td>App doesn't show tap-to-pay button</td>
                <td><code>softpos_enabled</code> false or status not Active</td>
                <td>Enable SoftPOS toggle and activate. Force re-sync in app.</td>
            </tr>
            <tr>
                <td>SDK fails to initialize on device</td>
                <td>Wrong TID or MID entered</td>
                <td>Verify TID/MID in NearPay portal. Edit terminal and correct.</td>
            </tr>
            <tr>
                <td>SoftPOS Bilateral Billing section not visible</td>
                <td>SoftPOS not enabled on terminal</td>
                <td>Enable SoftPOS toggle in SoftPOS Settings tab first</td>
            </tr>
            <tr>
                <td>Fees not calculated on transactions</td>
                <td>Billing rates all zero</td>
                <td>Edit terminal → Fees &amp; Settlement → set Mada and Visa/MC rates</td>
            </tr>
            <tr>
                <td>Merchant rate &lt; gateway rate validation error</td>
                <td>Gateway rate exceeds merchant rate</td>
                <td>Platform margin cannot be negative. Ensure merchant ≥ gateway for each fee.</td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>6 — API Quick Reference</h2>

    <div class="api-row">
        <span class="method-badge method-post">POST</span>
        <span class="api-path">/api/admin/terminals/{id}/activate-softpos</span>
        <span style="color:#555;">Activate SoftPOS on a terminal (provider-aware)</span>
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
        <span style="color:#555;">Update bilateral billing rates</span>
    </div>
    <div class="api-row">
        <span class="method-badge method-get">GET</span>
        <span class="api-path">/api/admin/terminals/{id}</span>
        <span style="color:#555;">View terminal with nearpay_tid, softpos_provider, billing</span>
    </div>

    <!-- Print button (bottom) -->
    <div class="no-print" style="margin-top: 32px;">
        <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <span>NEARPAY-SETUP-001</span>
        <span>Confidential. Not for distribution outside Wameed operations team.</span>
        <span>© {{ date('Y') }} Wameed</span>
    </div>

</div>
</body>
</html>
