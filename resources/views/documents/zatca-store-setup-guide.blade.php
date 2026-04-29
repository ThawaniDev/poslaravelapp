<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZATCA Store Setup Guide — Wameed POS</title>
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
        .required { color: #e74c3c; font-weight: 700; }
        .optional { color: #888; }
        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-req  { background: #fde8e8; color: #c0392b; }
        .badge-opt  { background: #eaf4fd; color: #2980b9; }

        /* ── Environments ── */
        .env-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 14px 0; }
        .env-card {
            border: 1px solid #dde3ef;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .env-card .env-name { font-weight: 700; font-size: 13px; margin-bottom: 4px; }
        .env-card p { font-size: 11.5px; color: #555; }
        .env-card.sandbox  { border-top: 3px solid #2980b9; }
        .env-card.sim      { border-top: 3px solid #8e44ad; }
        .env-card.prod     { border-top: 3px solid #27ae60; }
        .env-card.sandbox  .env-name { color: #2980b9; }
        .env-card.sim      .env-name { color: #8e44ad; }
        .env-card.prod     .env-name { color: #27ae60; }

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

        /* ── Renewal section ── */
        .renewal-flow {
            display: flex;
            gap: 0;
            align-items: center;
            margin: 14px 0;
            flex-wrap: wrap;
        }
        .rf-step {
            background: #ebf5fb;
            border: 1px solid #aed6f1;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 11.5px;
            text-align: center;
            min-width: 120px;
        }
        .rf-arrow {
            font-size: 18px;
            color: #1b4f72;
            padding: 0 6px;
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

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .page { padding: 24px 32px; }
            .no-print { display: none; }
            h2 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th  { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
            Document: ZATCA-SETUP-001<br>
            Version: 2.0<br>
            Date: {{ date('d M Y') }}<br>
            Confidential — Internal Use Only
        </div>
    </div>

    <div class="doc-title">ZATCA E-Invoicing — Store Setup Guide</div>
    <div class="doc-subtitle">Step-by-step instructions for Wameed staff to onboard and configure a new store for ZATCA Phase 2 compliance.</div>

    <!-- Callout -->
    <div class="callout warning">
        <div class="callout-icon">⏱</div>
        <p><strong>OTP expiry:</strong> Once generated on the Fatoora portal, the OTP is valid for <strong>1 hour only</strong>. Coordinate with the client and complete the enrollment step in the admin panel before the OTP expires.</p>
    </div>

    <div class="callout success">
        <div class="callout-icon">✓</div>
        <p><strong>New in v2.0:</strong> Each store certificate now stores its own environment (developer-portal / simulation / production) and API URL. <strong>Different stores on the same server can be on different environments simultaneously</strong> — e.g. Store A live in production while Store B is being tested in simulation. The system routes every API call to the correct ZATCA endpoint per certificate, automatically.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>1 — Before You Start: Information to Collect from the Client</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">Collect all required fields from the client before opening the admin panel. Missing information will block enrollment.</p>

    <table>
        <thead>
            <tr><th>Field</th><th>Description</th><th>Example</th><th>Required?</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>VAT Number (TIN)</strong></td>
                <td>15-digit tax identification number. Starts and ends with the digit 3.</td>
                <td>300000000000003</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Commercial Registration No.</strong></td>
                <td>CR number from the Ministry of Commerce.</td>
                <td>1010000001</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Business Name (Arabic)</strong></td>
                <td>Official name exactly as registered with ZATCA.</td>
                <td>شركة النجم للتجارة</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Business Name (English)</strong></td>
                <td>English transliteration or official English name.</td>
                <td>Al-Najm Trading Co.</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>City</strong></td>
                <td>City of the store location.</td>
                <td>Riyadh</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>District</strong></td>
                <td>District / neighborhood.</td>
                <td>Al-Olaya</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Street</strong></td>
                <td>Street name.</td>
                <td>King Fahd Road</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Building Number</strong></td>
                <td>4-digit building number.</td>
                <td>8228</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Postal Code</strong></td>
                <td>5-digit Saudi postal code.</td>
                <td>12211</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>ERAD Username</strong></td>
                <td>Client's TIN or email registered on the Fatoora portal. <em>Needed by the client to log into Fatoora — do not share your own credentials.</em></td>
                <td>300000000000003</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
            <tr>
                <td><strong>Target Environment</strong></td>
                <td>Which environment to enroll into (see Section 2).</td>
                <td>Production</td>
                <td><span class="badge badge-req">Required</span></td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>2 — Choose the Right Environment</h2>

    <div class="env-grid">
        <div class="env-card sandbox">
            <div class="env-name">Developer Portal</div>
            <p>Stub mode — no real HTTP calls to ZATCA. Issues a fake CCSID for local QA. <strong>Cannot get a real PCSID</strong> and cannot submit live invoices. Wameed internal testing only.</p>
        </div>
        <div class="env-card sim">
            <div class="env-name">Simulation</div>
            <p>ZATCA's official simulation environment. Real CCSID + real PCSID, no legal weight. <strong>Use this for client pre-production validation.</strong> OTP is generated on <strong>fatoora.zatca.gov.sa</strong> (Simulation mode).</p>
        </div>
        <div class="env-card prod">
            <div class="env-name">Production</div>
            <p>Live ZATCA environment. Real legal invoices. Use only after the simulation onboarding has passed all 6 compliance invoice types. OTP is generated on the production <strong>fatoora.zatca.gov.sa</strong> portal.</p>
        </div>
    </div>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p>All three environments are <strong>independent</strong>. A device enrolled in Simulation is not active in Production and vice versa — you must enroll separately for each. The certificate stores which environment it belongs to, so you don't need to change any global setting when switching between stores.</p>
    </div>

    <div class="callout warning">
        <div class="callout-icon">⚠</div>
        <p><strong>Standard onboarding flow:</strong> Developer Portal (internal QA) → Simulation (run 6 compliance tests, then exchange CCSID for PCSID) → Production. Skipping the simulation compliance test phase is grounds for ZATCA rejecting the production application.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>3 — Client Action: Generate OTP on Fatoora Portal</h2>

    <p style="margin-bottom:12px;font-size:12.5px;">The client must perform this step themselves using their own ERAD credentials. They cannot share their password with you.</p>

    <p style="font-size:12.5px;margin-bottom:8px;"><strong>Developer Portal</strong> environment does not require a real OTP — the system uses a stub OTP and self-signed certificate. Skip this section if enrolling in Developer Portal.</p>

    <h3>For Simulation or Production environment</h3>
    <ol class="steps">
        <li><p>Open <strong>https://fatoora.zatca.gov.sa/</strong> and log in with ERAD credentials (TIN or registered email + password).</p></li>
        <li><p>For <strong>Simulation</strong>: click <strong>"FATOORA Portal Simulation"</strong> in the top-right corner, accept the terms, then proceed.</p></li>
        <li><p>Click <strong>"Onboard new solution unit/device"</strong>.</p></li>
        <li><p>Enter <strong>1</strong> for the number of OTP codes to generate (one OTP per store device).</p></li>
        <li><p>The portal displays the OTP code. The client must <strong>copy it immediately</strong> — it is valid for <strong>1 hour</strong>.</p></li>
        <li><p>The client shares the OTP with the Wameed operator securely (call, secure message). <strong>Do not ask them to email it.</strong></p></li>
    </ol>

    <div class="callout danger">
        <div class="callout-icon">🔒</div>
        <p><strong>Security:</strong> The OTP is a one-time credential that grants certificate issuance rights. Treat it like a password. Do not log it, paste it in tickets, or share it in group chats.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>4 — Admin Panel: Configure & Enroll the Store</h2>

    <p style="margin-bottom:12px;font-size:12.5px;">Open the Wameed POS admin panel (<code>/admin</code>). Navigate to <strong>ZATCA → Store Setup</strong>.</p>

    <h3>Step A — Fill in store configuration</h3>
    <ol class="steps">
        <li><p>Select the store from the <strong>Store</strong> dropdown.</p></li>
        <li><p>Under <strong>Tax Identity</strong>, fill in all fields collected in Section 1: VAT Number, CR Number, business names, address fields.</p></li>
        <li><p>Under <strong>Integration</strong>, set the <strong>Environment</strong> (Developer Portal / Simulation / Production). This will be saved on the certificate — every API call for this store is routed to the matching ZATCA endpoint automatically.</p></li>
        <li><p>Leave the <strong>OTP</strong> field blank for now — it is only needed for enrollment (next step). If you paste the OTP here and save, it is stored encrypted and will be used during enrollment.</p></li>
        <li><p>Toggle <strong>Auto-submit invoices</strong> ON unless the client has requested manual submission.</p></li>
        <li><p>Click <strong>Save Configuration</strong>.</p></li>
    </ol>

    <h3>Step B — Enroll the store (uses the OTP)</h3>
    <ol class="steps">
        <li><p>On the same Store Setup page, click the <strong>"Enroll Now"</strong> button in the top-right header.</p></li>
        <li><p>A dialog will appear. Choose the <strong>Environment</strong>:</p>
            <ul style="margin:6px 0 0 14px;font-size:12.5px;">
                <li><strong>Developer Portal</strong> — internal QA. Fake CCSID, no real ZATCA call. OTP can be any value (e.g. <code>123456</code>).</li>
                <li><strong>Simulation</strong> — real CCSID from ZATCA simulation. Use the OTP the client generated on Fatoora Simulation.</li>
                <li><strong>Production</strong> — real CCSID from ZATCA production. Use the OTP from the production Fatoora portal.</li>
            </ul>
        </li>
        <li><p>Enter the <strong>OTP</strong> and click <strong>Submit</strong>. The system will call the matching ZATCA endpoint and obtain a <strong>CCSID</strong> (Compliance Cryptographic Stamp Identifier). The environment + API URL are stored on the certificate, so future calls for this store always go to the right place.</p></li>
        <li><p>If enrollment succeeds, a green success notification appears. The certificate is now visible under <strong>ZATCA → Certificates</strong>.</p></li>
    </ol>

    <div class="callout warning">
        <div class="callout-icon">⚠</div>
        <p><strong>If enrollment fails:</strong> Check the error message. Common causes: expired OTP (ask client to generate a new one), wrong environment selected, or VAT number mismatch. Fix the issue and retry.</p>
    </div>

    <h3>Step C — Provision a device</h3>
    <ol class="steps">
        <li><p>After successful enrollment, click <strong>"Provision Device"</strong> in the header.</p></li>
        <li><p>Confirm the environment and click Submit.</p></li>
        <li><p>The system creates a virtual EGS device and generates an <strong>Activation Code</strong>.</p></li>
        <li><p>The activation code is shown on the Store Setup page under <strong>Current Device</strong>. Copy and record it — the POS app needs it on first launch.</p></li>
    </ol>

    <h3>Step D — Run the 6 compliance invoice tests <span class="badge badge-req">Required for Simulation/Production</span></h3>
    <p style="font-size:12.5px;margin-bottom:8px;">ZATCA will not issue a Production CSID until your CCSID has successfully submitted all <strong>6 mandatory invoice types</strong> against the compliance endpoint:</p>
    <ul style="margin:0 0 10px 18px;font-size:12.5px;">
        <li>Simplified (B2C) invoice</li>
        <li>Standard (B2B) invoice</li>
        <li>Credit note — Simplified</li>
        <li>Credit note — Standard</li>
        <li>Debit note — Simplified</li>
        <li>Debit note — Standard</li>
    </ul>
    <ol class="steps">
        <li><p>Open <strong>ZATCA → Certificates</strong> in the admin panel.</p></li>
        <li><p>Click the orange <strong>“Run All 6 Compliance Tests”</strong> button (rocket icon) in the top-right header.</p></li>
        <li><p>Pick the store you just enrolled and click <strong>Submit</strong>. The system will sequentially submit all 6 invoice types against the compliance endpoint.</p></li>
        <li><p>A summary notification will show ✅ / ❌ per type. <strong>All 6 must be ✅</strong> before the next step.</p></li>
        <li><p>If any are ❌, open the failing invoice in <strong>ZATCA → Invoices</strong>, read the ZATCA response message, fix the underlying data, and re-run.</p></li>
    </ol>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p><strong>Re-running the suite:</strong> ZATCA tracks compliance progress server-side per CCSID. If you re-run after a previous successful submission, the already-completed invoice types return <em>“Compliance check already completed for …”</em> — this counts as passed and does <strong>not</strong> require resubmission. Only the types you have not yet submitted in this CCSID's lifecycle need to actually clear ZATCA in the new run.</p>
    </div>

    <h3>Step E — Exchange CCSID for PCSID (Production CSID) <span class="badge badge-req">Required for Simulation/Production</span></h3>
    <ol class="steps">
        <li><p>Once all 6 compliance tests pass, go to <strong>ZATCA → Certificates</strong> and click the green <strong>“Get Production Certificate (PCSID)”</strong> button (shield icon) in the top-right header.</p></li>
        <li><p>Pick the store. The system calls ZATCA's <code>/production/csids</code> endpoint, exchanging the CCSID for a long-lived <strong>PCSID</strong>. The new production certificate inherits the same environment as the compliance cert (simulation CCSID → simulation PCSID; production CCSID → production PCSID).</p></li>
        <li><p>The compliance cert is automatically retired (status: Expired). The new production cert appears in <strong>ZATCA → Certificates</strong> with status <strong>Active</strong>.</p></li>
    </ol>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p><strong>Developer Portal stores</strong> skip Steps D and E entirely — they remain on the self-signed compliance cert and are not capable of submitting real invoices.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>5 — Verification: Confirm Everything Is Working</h2>

    <ol class="steps">
        <li><p>Go to <strong>ZATCA → Certificates</strong>. Confirm a certificate for this store is listed with status <strong>Active</strong>.</p></li>
        <li><p>Go to <strong>ZATCA → Devices</strong>. Confirm the device shows <strong>Active</strong> and <strong>Tampered = No</strong>.</p></li>
        <li><p>Go to <strong>ZATCA → Overview</strong>. The store should appear in the cross-tenant summary with a healthy status.</p></li>
        <li><p>Ask the store to issue a test invoice. Check <strong>ZATCA → Invoices</strong> — the invoice should appear with status <strong>Accepted</strong> or <strong>Reported</strong> within a few seconds.</p></li>
    </ol>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>6 — Certificate Renewal</h2>

    <p style="margin-bottom:12px;font-size:12.5px;">ZATCA certificates expire. The system shows expiry dates in <strong>ZATCA → Certificates</strong> (highlighted red when &lt;30 days remain).</p>

    <div class="renewal-flow">
        <div class="rf-step">Client generates<br><strong>new OTP</strong><br>on Fatoora portal<br><small>(click "Renewing CSID")</small></div>
        <div class="rf-arrow">→</div>
        <div class="rf-step">Admin panel<br><strong>ZATCA → Certificates</strong><br>click <strong>Renew</strong><br>on the certificate row</div>
        <div class="rf-arrow">→</div>
        <div class="rf-step">Or use<br><strong>Store Setup → Renew Certificate</strong><br>header button</div>
        <div class="rf-arrow">→</div>
        <div class="rf-step">New certificate issued<br>old one revoked<br>invoicing continues<br>without interruption</div>
    </div>

    <div class="callout info">
        <div class="callout-icon">ℹ</div>
        <p>For renewal, the client must click <strong>"Renewing Existing Cryptographic Stamp Identifier (CSID)"</strong> on the Fatoora portal (not "Onboard new solution"). This generates a renewal OTP — also valid for 1 hour.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>7 — Revoking a Device or Certificate</h2>

    <ol class="steps">
        <li><p>To revoke a certificate: go to <strong>ZATCA → Certificates</strong>, find the certificate, click <strong>Revoke</strong>. Confirm the action. The status changes to Revoked.</p></li>
        <li><p>To revoke a device on the Fatoora portal: the client logs in, goes to <strong>"View list of solutions and devices"</strong>, selects the device, and clicks <strong>Revoke</strong>. <strong>This action is irreversible</strong> — the device cannot be reactivated; it must be re-onboarded as new.</p></li>
        <li><p>After revocation, provision a new device (Section 4, Step C) and re-enroll if required.</p></li>
    </ol>

    <div class="callout danger">
        <div class="callout-icon">🚫</div>
        <p><strong>Do not revoke</strong> a production certificate or device unless explicitly instructed. Revocation immediately stops all invoice submission for that store. Coordinate with the client before proceeding.</p>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>8 — Troubleshooting Quick Reference</h2>

    <table>
        <thead>
            <tr><th>Symptom</th><th>Likely Cause</th><th>Fix</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Enrollment fails with "OTP invalid"</td>
                <td>OTP has expired (1-hour limit) or was typed incorrectly</td>
                <td>Ask client to generate a fresh OTP and retry within 1 hour</td>
            </tr>
            <tr>
                <td>Enrollment fails with "VAT mismatch"</td>
                <td>VAT number entered doesn't match the ERAD account</td>
                <td>Confirm the VAT number with the client</td>
            </tr>
            <tr>
                <td>Certificate status shows Expired</td>
                <td>Certificate past its expiry date</td>
                <td>Perform certificate renewal (Section 6)</td>
            </tr>
            <tr>
                <td>Device shows Tampered</td>
                <td>Hash chain integrity failure detected</td>
                <td>Go to ZATCA → Devices → Reset Tamper Flag, then verify chain</td>
            </tr>
            <tr>
                <td>Invoices stuck in Pending</td>
                <td>Network issue or ZATCA gateway timeout</td>
                <td>ZATCA → Invoices → click Retry on affected rows</td>
            </tr>
            <tr>
                <td>Invoices Rejected by ZATCA</td>
                <td>Invoice data error (missing fields, wrong format)</td>
                <td>View XML in ZATCA → Invoices, check ZATCA response message, fix data, retry</td>
            </tr>
            <tr>
                <td>Store not appearing in Overview</td>
                <td>Store not yet enrolled or enrollment failed</td>
                <td>Repeat Section 4 from Step A</td>
            </tr>
        </tbody>
    </table>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>9 — Pre-Go-Live Checklist</h2>

    <p style="margin-bottom:10px;font-size:12.5px;">Complete all items before switching a store to Production environment.</p>

    <ul class="checklist">
        <li>Client's VAT number verified (15 digits, starts and ends with 3)</li>
        <li>Commercial Registration number confirmed</li>
        <li>Business name in Arabic matches ZATCA registration exactly</li>
        <li>Full address (city, district, street, building number, postal code) entered</li>
        <li>Simulation enrollment completed — CCSID issued by ZATCA simulation</li>
        <li>All 6 compliance invoice types submitted in Simulation — every one Accepted (“Run All 6 Compliance Tests” shows ✅ × 6)</li>
        <li>CCSID exchanged for PCSID in Simulation (“Get Production Certificate (PCSID)” button) — production cert active</li>
        <li>Production OTP obtained from client (generated on production Fatoora portal)</li>
        <li>Production enrollment completed — certificate status Active</li>
        <li>All 6 compliance invoice types re-run in Production environment — every one Accepted</li>
        <li>CCSID exchanged for PCSID in Production (“Get Production Certificate (PCSID)” button)</li>
        <li>Device provisioned and activation code recorded</li>
        <li>Auto-submit enabled (unless client requested manual)</li>
        <li>First production invoice submitted and confirmed Accepted or Reported by ZATCA</li>
        <li>Client informed of certificate expiry date and renewal process</li>
    </ul>

    <!-- ═══════════════════════════════════════════════ -->
    <h2>Reference: ZATCA API Endpoints</h2>

    <h3>Production (fatoora.zatca.gov.sa)</h3>
    <table>
        <thead><tr><th>Purpose</th><th>Endpoint</th></tr></thead>
        <tbody>
            <tr><td>Request compliance CSID</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/core/compliance</span></td></tr>
            <tr><td>Compliance invoice checks</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/core/compliance/invoices</span></td></tr>
            <tr><td>Request / renew production CSID</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/core/production/csids</span></td></tr>
            <tr><td>Reporting (simplified invoices)</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/core/invoices/reporting/single</span></td></tr>
            <tr><td>Clearance (standard/B2B invoices)</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/core/invoices/clearance/single</span></td></tr>
        </tbody>
    </table>

    <h3>Simulation (fatoora.zatca.gov.sa — Simulation mode)</h3>
    <table>
        <thead><tr><th>Purpose</th><th>Endpoint</th></tr></thead>
        <tbody>
            <tr><td>Request compliance CSID</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance</span></td></tr>
            <tr><td>Compliance invoice checks</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices</span></td></tr>
            <tr><td>Request / renew production CSID</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/production/csids</span></td></tr>
            <tr><td>Reporting</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/reporting/single</span></td></tr>
            <tr><td>Clearance</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/clearance/single</span></td></tr>
        </tbody>
    </table>

    <h3>Developer Portal (stub — internal QA)</h3>
    <table>
        <thead><tr><th>Purpose</th><th>Endpoint</th></tr></thead>
        <tbody>
            <tr><td>All endpoints</td><td><span class="endpoint">https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/...</span></td></tr>
            <tr><td colspan="2" style="font-size:11.5px;color:#666;">No real HTTP traffic in stub mode — the system returns canned responses and self-signs the certificate.</td></tr>
        </tbody>
    </table>

    <div class="callout info" style="margin-top:16px;">
        <div class="callout-icon">ℹ</div>
        <p>The Wameed POS system selects the correct endpoint automatically based on the <strong>Environment</strong> stored on each certificate. You do not need to configure API URLs manually — and you do not need to change the global server config when onboarding a new store on a different environment.</p>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <div>Wameed POS — Internal Operations Guide — ZATCA-SETUP-001</div>
        <div>Confidential. Not for distribution outside Wameed operations team.</div>
        <div>{{ date('Y') }} Wameed</div>
    </div>

</div>

<div class="no-print" style="text-align:center;padding:20px;">
    <button onclick="window.print()" style="padding:10px 24px;background:#1b4f72;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:pointer;">🖨 Print / Save as PDF</button>
</div>

</body>
</html>
