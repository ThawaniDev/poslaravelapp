<x-filament-panels::page>

{{-- ── Top bar ─────────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900">
            <x-heroicon-o-credit-card class="w-5 h-5 text-orange-600 dark:text-orange-300" />
        </span>
        <div>
            <h1 class="text-lg font-bold text-gray-900 dark:text-white">EdfaPay SoftPOS — Terminal Setup Guide</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Step-by-step instructions for onboarding an EdfaPay tap-to-pay terminal in the admin panel.</p>
        </div>
    </div>
    <a href="{{ route('admin.documents.edfapay-terminal-setup-guide') }}" target="_blank"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-orange-600 text-white text-sm font-medium hover:bg-orange-700 transition no-print">
        <x-heroicon-o-printer class="w-4 h-4" />
        Print / Save PDF
    </a>
</div>

{{-- ── Info callout ─────────────────────────────────────────────────────── --}}
<div class="rounded-lg border border-orange-200 bg-orange-50 dark:bg-orange-950 dark:border-orange-800 p-4 mb-6 flex gap-3">
    <x-heroicon-o-information-circle class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" />
    <p class="text-sm text-orange-800 dark:text-orange-200">
        <strong>EdfaPay</strong> is the second supported SoftPOS provider. Unlike NearPay, it uses a <strong>Terminal Token</strong> (a long hex string from the EdfaPay merchant portal) instead of a TID/MID pair. The token is stored <strong>encrypted at rest</strong> and delivered to the Flutter app over a secured API call for SDK initialization.
    </p>
</div>

{{-- ── Section 1: Prerequisites ─────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">1</span>
        <h2 class="text-sm font-semibold text-white">Information to Collect Before Setup</h2>
    </div>
    <div class="p-5">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">You need the following before setting up the terminal in the admin panel. The token must be fetched from the EdfaPay merchant portal by a Wameed operator or the client.</p>
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
                        <td class="px-3 py-2 font-medium">EdfaPay Terminal Token</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay merchant portal → Terminals → Generate Token</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500 max-w-xs truncate">D08BE4C0FE041A15…</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Acquirer Source</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Client's acquiring bank (HALA, Al Rajhi, SNB, Geidea)</td>
                        <td class="px-3 py-2 text-gray-500">Geidea</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Mada Merchant Rate (%)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay / acquirer contract</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">0.006000 (= 0.6%)</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Mada Gateway Rate (%)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay contract — must be ≤ Merchant Rate</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">0.004000 (= 0.4%)</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Visa/MC Merchant Rate (%)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay / acquirer contract (optional: for mixed % + fixed model)</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">0.025 (= 2.5%) or 0</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs font-semibold">Optional</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Visa/MC Merchant Fee (SAR)</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay / acquirer contract</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">1.000 SAR</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-xs font-semibold">Required</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Device Model & Platform</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Physical device</td>
                        <td class="px-3 py-2 text-gray-500">Samsung Galaxy / Android 13</td>
                        <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs font-semibold">Optional</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3 rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-yellow-950 dark:border-yellow-800 p-3 flex gap-2">
            <x-heroicon-o-shield-exclamation class="w-4 h-4 text-yellow-600 flex-shrink-0 mt-0.5" />
            <p class="text-xs text-yellow-800 dark:text-yellow-200"><strong>Token security:</strong> The EdfaPay terminal token is a privileged credential. Treat it like a password — do not paste it in tickets, Slack, or email. The admin panel stores it encrypted (Laravel encryption). It is never logged in plaintext.</p>
        </div>
    </div>
</div>

{{-- ── Section 2: How to get the EdfaPay token ────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">2</span>
        <h2 class="text-sm font-semibold text-white">How to Obtain the EdfaPay Terminal Token</h2>
    </div>
    <div class="p-5">
        <ol class="space-y-3 text-sm">
            @foreach([
                ['Log in to the <strong>EdfaPay merchant portal</strong> with your EdfaPay account credentials.'],
                ['Navigate to <strong>Terminals</strong> or <strong>Devices</strong> section.'],
                ['Find or create the terminal entry for the target device.'],
                ['Click <strong>Generate Token</strong> (or "SDK Token" depending on portal version). A long hex token string is displayed.'],
                ['<strong>Copy the token immediately</strong> — it may not be retrievable again after the page is closed.'],
                ['Store it securely until you paste it into the admin panel (next section). Do not email or log it.'],
            ] as $i => [$step])
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">{{ $i+1 }}</span>
                <p class="text-gray-700 dark:text-gray-300">{!! $step !!}</p>
            </li>
            @endforeach
        </ol>
    </div>
</div>

{{-- ── Section 3: Admin Panel Steps ────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">3</span>
        <h2 class="text-sm font-semibold text-white">Setup Steps in Admin Panel</h2>
    </div>
    <div class="p-5 space-y-5">

        {{-- Step A --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">A</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Create the register — Basic Info tab</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Go to <strong class="text-gray-800 dark:text-gray-200">Terminals</strong> in the left sidebar → click <strong>New Terminal</strong>.</li>
                    <li>Select the <strong>Store</strong> this register belongs to.</li>
                    <li>Set a <strong>Terminal Name</strong> (e.g. "Cashier 2 — EdfaPay Tap").</li>
                    <li>Enter the <strong>Device ID</strong> (unique identifier for this device).</li>
                    <li>Set <strong>Platform</strong> to <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">android</code> (EdfaPay SDK targets Android).</li>
                    <li>Fill in optional hardware fields (NFC Capable ✓, Device Model, OS Version, Serial).</li>
                </ol>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step B --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">B</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">SoftPOS Settings tab — configure EdfaPay</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Click the <strong>SoftPOS Settings</strong> tab.</li>
                    <li>Toggle <strong>SoftPOS</strong> ON.</li>
                    <li>Set <strong>SoftPOS Provider</strong> → <span class="inline-block px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300 text-xs font-semibold">EdfaPay</span>. A new <strong>EdfaPay Terminal Token</strong> field appears.</li>
                    <li>Paste the token in the <strong>EdfaPay Terminal Token</strong> field (the field uses a masked / password input). It is stored encrypted automatically on save.</li>
                    <li>Set <strong>SoftPOS Status</strong> to <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">Pending</code> initially.</li>
                    <li>Under <strong>Acquirer Source</strong> section: select the acquirer bank, fill Acquirer Name and Reference.</li>
                </ol>
                <div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950 dark:border-blue-800 p-3 flex gap-2">
                    <x-heroicon-o-eye-slash class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                    <p class="text-xs text-blue-800 dark:text-blue-200">The token field is masked (password-type with reveal button). On the View page the system shows only whether a token <strong>is set or not</strong> (a key icon) — the raw token is never displayed after saving.</p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step C --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">C</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Fees & Settlement tab — configure bilateral billing rates</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Click the <strong>Fees & Settlement</strong> tab.</li>
                    <li><strong>Mada bilateral rates:</strong> Set Mada Merchant Rate (%) and Mada Gateway Rate (%) — gateway ≤ merchant.</li>
                    <li><strong>Visa/MC/Amex — mixed fee model:</strong> Set the Merchant Rate (%) and Gateway Rate (%) for a percentage component. Leave at 0 for fixed-only billing.</li>
                    <li>Set <strong>Visa/MC/Amex – Merchant Fee (SAR)</strong> — the fixed SAR component per transaction.</li>
                    <li>Set <strong>Visa/MC/Amex – Gateway Fee (SAR)</strong> — the portion paid to EdfaPay (≤ Merchant Fee). Platform margin = Merchant Fee − Gateway Fee.</li>
                    <li>Final fee formula: <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">platform_fee = (amount × merchant_rate) + merchant_fee</code></li>
                    <li>Set Settlement Cycle, Bank Name, and IBAN.</li>
                </ol>
                <div class="mt-2 rounded-lg border border-green-200 bg-green-50 dark:bg-green-950 dark:border-green-800 p-3 flex gap-2">
                    <x-heroicon-o-calculator class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" />
                    <p class="text-xs text-green-800 dark:text-green-200"><strong>Example (2.5% + 1 SAR):</strong> On a 200 SAR Visa transaction → merchant fee = (200 × 0.025) + 1.000 = <strong>6.000 SAR</strong>. If gateway rate = 2.0% + 0.500 SAR → gateway fee = (200 × 0.020) + 0.500 = 4.500 SAR. Platform margin = <strong>1.500 SAR</strong>.</p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step D --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">D</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Save the terminal</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Click <strong>Save</strong>. The token is encrypted and stored. The <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">edfapay_token_updated_at</code> timestamp is recorded for audit purposes.</p>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800" />

        {{-- Step E --}}
        <div class="flex gap-4">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center mt-0.5">E</div>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">Activate SoftPOS</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>In the Terminals list, click the <strong>⋮</strong> actions menu → <strong>Activate SoftPOS</strong>.</li>
                    <li>The system checks that a token is stored and an acquirer is set. If either is missing, a 422 error is returned with a descriptive message.</li>
                    <li>On success, <strong>softpos_status</strong> = <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 text-xs font-semibold">Active</span> and <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">softpos_activated_at</code> is recorded.</li>
                    <li>Alternatively via API: <code class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">POST /api/admin/terminals/{"{id}"}/activate-softpos</code> — you can also supply the token in the request body if not yet set: <code class="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">{"{edfapay_token: \"…\"}"}</code></li>
                </ol>
            </div>
        </div>

    </div>
</div>

{{-- ── Section 4: App provisioning ─────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">4</span>
        <h2 class="text-sm font-semibold text-white">Flutter App Provisioning</h2>
    </div>
    <div class="p-5">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">The Flutter POS app receives the EdfaPay token and silently initializes the EdfaPay SDK on first launch — no manual configuration is needed on the device.</p>
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
                        <td class="px-3 py-2 font-medium">Terminal Token</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">edfapay_token</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">EdfaPay SDK silent initialization</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">Token Updated At</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">edfapay_token_updated_at</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">App can detect token rotations and re-initialize SDK</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">SoftPOS Provider</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">softpos_provider = "edfapay"</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">App selects EdfaPay SDK (not NearPay)</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-medium">SoftPOS Enabled</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">softpos_enabled = true</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Shows the tap-to-pay button in POS checkout screen</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Section 5: Token rotation ────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">5</span>
        <h2 class="text-sm font-semibold text-white">Rotating / Updating the Token</h2>
    </div>
    <div class="p-5 space-y-3 text-sm text-gray-600 dark:text-gray-400">
        <p>If EdfaPay issues a new token for a terminal (e.g. security rotation, device replacement):</p>
        <ol class="list-decimal list-inside space-y-2">
            <li>Go to <strong class="text-gray-800 dark:text-gray-200">Terminals</strong> → find the terminal → click <strong>Edit</strong>.</li>
            <li>Navigate to <strong>SoftPOS Settings</strong> tab.</li>
            <li>Clear the <strong>EdfaPay Terminal Token</strong> field and paste the new token.</li>
            <li>Save. The system records the new <code>edfapay_token_updated_at</code> timestamp and an audit log entry is created automatically.</li>
            <li>The Flutter app detects the token change on its next config sync and re-initializes the SDK silently.</li>
        </ol>
        <div class="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950 dark:border-blue-800 p-3 flex gap-2">
            <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
            <p class="text-xs text-blue-800 dark:text-blue-200">To <strong>clear</strong> the token entirely: pass <code class="font-mono">null</code> via the API (<code>PATCH /api/admin/terminals/{"{id}"}</code> with <code>{"{edfapay_token: null}"}</code>). Clearing the token will make the app fall back to non-SoftPOS mode until a new token is set.</p>
        </div>
    </div>
</div>

{{-- ── Section 6: Verification checklist ──────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">6</span>
        <h2 class="text-sm font-semibold text-white">Verification Checklist</h2>
    </div>
    <div class="p-5">
        <ul class="space-y-2 text-sm">
            @foreach([
                'Terminal appears in Terminals list with SoftPOS icon ✓',
                'SoftPOS Provider shows EdfaPay (orange badge) on the view page',
                'SoftPOS Status shows Active (green badge)',
                'Token status shows key icon ✓ (set) on the view page — not the raw token',
                'Token Updated At timestamp is visible on view page',
                'Acquirer Source is set',
                'Mada bilateral rates configured (merchant ≥ gateway)',
                'Visa/MC fee rates configured (at minimum the fixed SAR merchant/gateway fees)',
                'Settlement IBAN + bank set',
                'Flutter app launches and shows tap-to-pay button in checkout screen',
                'A test SoftPOS transaction appears in SoftPOS Transactions → shows fee breakdown',
            ] as $item)
            <li class="flex items-start gap-2">
                <span class="flex-shrink-0 text-gray-400 mt-0.5">☐</span>
                <span class="text-gray-700 dark:text-gray-300">{{ $item }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- ── Section 7: Troubleshooting ──────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-800 dark:bg-gray-950 flex items-center gap-2">
        <span class="text-white font-bold text-sm">7</span>
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
                    <td class="px-3 py-2">"EdfaPay terminal token is not set" error on activation</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Token field was left empty or not saved</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Edit terminal → SoftPOS Settings → paste token → Save</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">Activate SoftPOS button not visible in row actions</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">SoftPOS not enabled, or status already Active, or missing acquirer</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Enable SoftPOS + set acquirer. Button appears when status ≠ Active and token exists.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">EdfaPay Token field not appearing in form</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Provider set to NearPay (or not selected)</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Set <strong>SoftPOS Provider</strong> = EdfaPay — the token field is conditionally shown</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">SDK fails to initialize — app crashes or shows error</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Token is invalid or expired in EdfaPay's system</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Regenerate token in EdfaPay portal → update terminal in admin panel</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">Mixed fee gives wrong platform margin</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Gateway rate > merchant rate</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">API validates merchant ≥ gateway. Fix the rate values to ensure margin ≥ 0.</td>
                </tr>
                <tr>
                    <td class="px-3 py-2">Token shows as not set in view page even after saving</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Encryption key mismatch (APP_KEY changed)</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">Re-enter the token. Check the audit log for the save event.</td>
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
            ['POST',  '/api/admin/terminals/{id}/activate-softpos',  'Activate; optionally supply edfapay_token in body'],
            ['POST',  '/api/admin/terminals/{id}/suspend-softpos',   'Suspend SoftPOS'],
            ['POST',  '/api/admin/terminals/{id}/deactivate-softpos','Deactivate SoftPOS'],
            ['PATCH', '/api/admin/terminals/{id}/softpos-billing',   'Update bilateral billing rates (all 6 rate fields)'],
            ['PATCH', '/api/admin/terminals/{id}',                   'Update terminal fields including edfapay_token'],
            ['GET',   '/api/admin/terminals/{id}',                   'View: edfapay_token (decrypted), token_updated_at, billing'],
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
