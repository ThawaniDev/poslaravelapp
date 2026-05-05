<x-filament-panels::page>

{{-- ── Top bar ─────────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900">
            <x-heroicon-o-cpu-chip class="w-5 h-5 text-blue-600 dark:text-blue-300" />
        </span>
        <div>
            <h1 class="text-lg font-bold text-gray-900 dark:text-white">NearPay SoftPOS — Terminal Setup Guide</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Step-by-step instructions for onboarding a NearPay tap-to-pay terminal in the admin panel.</p>
        </div>
    </div>
    <a href="{{ route('admin.documents.nearpay-terminal-setup-guide') }}" target="_blank"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition no-print">
        <x-heroicon-o-printer class="w-4 h-4" />
        Print / Save PDF
    </a>
</div>

{{-- ── Info callout ─────────────────────────────────────────────────────── --}}
<div class="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950 dark:border-blue-800 p-4 mb-6 flex gap-3">
    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
    <p class="text-sm text-blue-800 dark:text-blue-200">
        <strong>NearPay</strong> is the default SoftPOS provider. It requires a <strong>Terminal ID (TID)</strong> and <strong>Merchant ID (MID)</strong> issued by NearPay during device onboarding. The Flutter app uses these to initialize the NearPay SDK for tap-to-pay.
    </p>
</div>

{{-- ── Section 1: Information to collect ─────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">1</span>
        <h2 class="text-sm font-semibold text-white">Information to Collect Before Setup</h2>
    </div>
    <div class="p-5">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Collect all required credentials from NearPay and the client before opening the admin panel.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Field</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Where to Get It</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Example</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Required?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td class="px-3 py-2 font-medium">NearPay Terminal ID (TID)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">NearPay merchant portal → My Terminals</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">TID-ABC123</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Merchant ID (MID)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">NearPay merchant portal → Account Settings</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">MID-999001</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Acquirer Source</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Client's acquiring bank (HALA, Al Rajhi, SNB, Geidea)</td>
                        <td class="px-3 py-2 text-gray-500">HALA</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Device Model & OS</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Physical device</td>
                        <td class="px-3 py-2 text-gray-500">Samsung Galaxy / Android 13</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs font-semibold">Optional</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Mada Merchant Rate</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Contract with acquirer (e.g. 0.6% = 0.006)</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">0.006000</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Mada Gateway Rate</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">NearPay contract — must be ≤ Merchant Rate</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">0.004000</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Visa/MC Merchant Fee (SAR)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Contract with acquirer</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">1.000 SAR</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Section 2: Steps ─────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">2</span>
        <h2 class="text-sm font-semibold text-white">Setup Steps in Admin Panel</h2>
    </div>
    <div class="p-5 space-y-5">

        {{-- Step A --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">A</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Create the register — Basic Info tab</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Go to <strong class="text-gray-800 dark:text-gray-200">Terminals</strong> in the left sidebar → click <strong>New Terminal</strong>.</li>
                    <li>Select the <strong>Store</strong> this register belongs to.</li>
                    <li>Set a <strong>Terminal Name</strong> (e.g. "Cashier 1 — Tap to Pay").</li>
                    <li>Enter the <strong>Device ID</strong> (unique, can be the device serial or a custom ID).</li>
                    <li>Set <strong>Platform</strong> to <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">android</code> (NearPay SDK requires Android).</li>
                    <li>Fill in optional device hardware fields (Device Model, OS Version, Serial Number, NFC Capable ✓).</li>
                </ol>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step B --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">B</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">SoftPOS Settings tab — configure NearPay</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Click the <strong>SoftPOS Settings</strong> tab.</li>
                    <li>Toggle <strong>SoftPOS</strong> ON — additional fields appear.</li>
                    <li>Set <strong>SoftPOS Provider</strong> → <span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs font-semibold">NearPay</span>.</li>
                    <li>Set <strong>SoftPOS Status</strong> to <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">Pending</code> initially.</li>
                    <li>Enter the <strong>NearPay Terminal ID (TID)</strong> from the NearPay portal.</li>
                    <li>Enter the <strong>Merchant ID (MID)</strong> from the NearPay portal.</li>
                    <li>Under <strong>Acquirer Source</strong> section: select the acquirer bank, optionally fill Acquirer Name and Reference.</li>
                </ol>
                <div class="mt-2 rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-yellow-950 dark:border-yellow-800 p-3 flex gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-yellow-600 flex-shrink-0 mt-0.5" />
                    <p class="text-xs text-yellow-800 dark:text-yellow-200">The <strong>Activate SoftPOS</strong> button (in the terminal row actions) will be blocked until both TID and Acquirer Source are set.</p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step C --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">C</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Fees & Settlement tab — configure bilateral billing rates</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Click the <strong>Fees & Settlement</strong> tab. The <strong>SoftPOS Bilateral Billing</strong> section is visible only when SoftPOS is enabled.</li>
                    <li>Set <strong>Mada – Merchant Rate</strong> (decimal, e.g. <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">0.006</code> = 0.6%). This is the rate charged to the merchant.</li>
                    <li>Set <strong>Mada – Gateway Rate</strong> (must be ≤ Merchant Rate, e.g. <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">0.004</code> = 0.4%). The platform margin = merchant − gateway.</li>
                    <li>Set <strong>Visa/MC/Amex – Merchant Fee (SAR)</strong> — fixed SAR fee per transaction charged to merchant (e.g. <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">1.000</code>).</li>
                    <li>Set <strong>Visa/MC/Amex – Gateway Fee (SAR)</strong> — fixed SAR fee paid to gateway (must be ≤ Merchant Fee, e.g. <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">0.500</code>).</li>
                    <li>Optionally set <strong>Visa/MC/Amex – Merchant Rate (%)</strong> and <strong>Gateway Rate (%)</strong> for a mixed (% + fixed) model. Leave at 0 for fixed-only.</li>
                    <li>Under <strong>Settlement</strong>: set settlement cycle (T+1 / T+2 / T+3 / weekly), bank name, and IBAN.</li>
                </ol>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step D --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">D</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Save the terminal</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Click <strong>Save</strong>. All three tabs are saved together.</li>
                    <li>The terminal now appears in the Terminals list with SoftPOS status <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">Pending</code>.</li>
                </ol>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step E --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">E</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Activate SoftPOS</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Back in the Terminals list, find the newly created terminal.</li>
                    <li>Click the <strong>⋮</strong> actions menu → <strong>Activate SoftPOS</strong>.</li>
                    <li>Confirm the dialog. Status changes to <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 text-xs font-semibold">Active</span>.</li>
                    <li>Alternatively, use the API: <code class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-xs font-mono">POST /api/admin/terminals/{"{id}"}/activate-softpos</code></li>
                </ol>
            </div>
        </div>

    </div>
</div>

{{-- ── Section 3: App provisioning ─────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">3</span>
        <h2 class="text-sm font-semibold text-white">Flutter App Provisioning</h2>
    </div>
    <div class="p-5">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">After the admin setup is complete, the Flutter POS app will auto-provision the NearPay SDK on first launch using these credentials.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">What the app receives</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">API field</th>
                        <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Used for</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td class="px-3 py-2 font-medium">Terminal ID</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">nearpay_tid</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">NearPay SDK initialization (<code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">NearPay(tid:)</code>)</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Merchant ID</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">nearpay_mid</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Identifies the merchant account on NearPay</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">SoftPOS Provider</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">softpos_provider = "nearpay"</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">App decides which SDK to initialize</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">SoftPOS Enabled</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">softpos_enabled = true</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Shows/hides the tap-to-pay button in the POS checkout screen</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950 dark:border-blue-800 p-3 flex gap-2">
            <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
            <p class="text-xs text-blue-800 dark:text-blue-200">The app fetches register config via <code class="font-mono">GET /api/v1/register/config</code>. It checks <code>softpos_provider === "nearpay"</code> and loads the NearPay SDK using TID + MID. No manual configuration is needed on the device.</p>
        </div>
    </div>
</div>

{{-- ── Section 4: Verification ─────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">4</span>
        <h2 class="text-sm font-semibold text-white">Verification Checklist</h2>
    </div>
    <div class="p-5">
        <ul class="space-y-2 text-sm">
            @foreach([
                'Terminal appears in Terminals list with SoftPOS icon ✓',
                'SoftPOS Status shows Active (green badge)',
                'SoftPOS Provider shows NearPay (blue badge)',
                'NearPay TID and MID are visible on the terminal view page',
                'Acquirer Source is set',
                'Bilateral billing rates are configured (Mada % + Visa/MC SAR fees)',
                'Flutter app launches and shows the tap-to-pay button on checkout',
                'A test SoftPOS transaction appears in SoftPOS Transactions list after a test tap',
            ] as $item)
            <li class="flex items-start gap-2">
                <span class="flex-shrink-0 text-gray-400 mt-0.5">☐</span>
                <span class="text-gray-700 dark:text-gray-300">{{ $item }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- ── Section 5: Troubleshooting ──────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">5</span>
        <h2 class="text-sm font-semibold text-white">Troubleshooting</h2>
    </div>
    <div class="p-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Symptom</th>
                    <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Likely Cause</th>
                    <th class="text-left px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Fix</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr>
                    <td class="px-3 py-2">Activate SoftPOS button not visible</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">TID or acquirer source not set</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Edit terminal → SoftPOS Settings → fill in TID + acquirer</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">App doesn't show tap-to-pay button</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">softpos_enabled</code> is false or status not Active</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Enable SoftPOS toggle and activate. Force re-sync in app.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">SDK fails to initialize on device</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Wrong TID or MID entered</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Verify TID/MID with NearPay portal. Edit terminal and update.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">Fees not calculated on SoftPOS transactions</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Billing rates all zero</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Edit terminal → Fees & Settlement → set Mada rates + Visa/MC fees</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">SoftPOS Bilateral Billing section not visible in fees tab</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">SoftPOS not enabled on the terminal</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Go to SoftPOS Settings tab, enable the toggle first</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">Merchant rate &lt; gateway rate validation error</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Gateway rate exceeds merchant rate</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Platform margin cannot be negative. Ensure merchant rate ≥ gateway rate.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── API quick ref ────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <x-heroicon-o-code-bracket class="w-4 h-4 text-gray-300" />
        <h2 class="text-sm font-semibold text-white">Useful API Endpoints</h2>
    </div>
    <div class="p-5 space-y-2">
        @foreach([
            ['POST',  '/api/admin/terminals/{id}/activate-softpos',  'Activate SoftPOS on a terminal (provider-aware)'],
            ['POST',  '/api/admin/terminals/{id}/suspend-softpos',   'Suspend SoftPOS'],
            ['POST',  '/api/admin/terminals/{id}/deactivate-softpos','Deactivate SoftPOS'],
            ['PATCH', '/api/admin/terminals/{id}/softpos-billing',   'Update bilateral billing rates'],
            ['GET',   '/api/admin/terminals/{id}',                   'View terminal + nearpay_tid, softpos_provider, billing'],
        ] as [$method, $path, $desc])
        <div class="flex items-start gap-3 text-sm">
            <span class="flex-shrink-0 font-mono text-xs px-2 py-0.5 rounded font-bold {{ $method === 'GET' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : ($method === 'POST' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300') }}">{{ $method }}</span>
            <code class="flex-shrink-0 text-xs font-mono text-gray-700 dark:text-gray-300">{{ $path }}</code>
            <span class="text-gray-500 dark:text-gray-400">{{ $desc }}</span>
        </div>
        @endforeach
    </div>
</div>

</x-filament-panels::page>
