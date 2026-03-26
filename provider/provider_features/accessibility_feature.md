# Accessibility — Comprehensive Feature Documentation

> **Scope:** Provider (Store-Level Flutter Desktop POS)  
> **Module:** Keyboard Navigation, High Contrast, Font Scaling, Screen Reader, Shortcuts  
> **Tech Stack:** Flutter 3.x Desktop · Windows Accessibility APIs  

---

## 1. Feature Overview

Accessibility ensures the POS system is usable by staff with varying abilities — visual impairments, motor difficulties, and cognitive preferences. The feature emphasizes keyboard-driven operation (critical for POS speed), scalable UI elements, and compliance with WCAG 2.1 AA guidelines where applicable.

### What This Feature Does
- **Full keyboard navigation** — every POS function accessible via keyboard; no mouse required
- **Keyboard shortcuts** — configurable shortcut keys for common actions (F1-F12, Ctrl+ combos)
- **Font scaling** — UI text scalable from 80% to 150% without layout breaking
- **High contrast mode** — high-contrast color themes for visually impaired users
- **Large touch targets** — POS buttons meet minimum 44px touch/click target
- **Screen reader compatibility** — Flutter Semantics labels for Windows Narrator / NVDA
- **Color-blind safe** — status indicators use shapes/icons in addition to colors
- **Reduced motion** — option to disable animations for motion-sensitive users
- **Audio feedback** — optional confirmation sounds for actions (beep on scan, chime on sale complete)
- **Focus indicators** — visible focus ring for keyboard navigation

---

## 2. Affected Features & Dependencies

### Features That This Feature Depends On
| Feature | Dependency |
|---|---|
| **POS Interface Customization** | Font size and theme settings |
| **Language & Localization** | RTL-safe accessibility features |

### Features That Depend on This Feature
| Feature | Dependency |
|---|---|
| **All UI Features** | Keyboard navigation, screen reader labels |
| **POS Terminal** | Keyboard shortcut-driven transaction flow |

### Features to Review After Changing This Feature
1. **Every screen** — keyboard navigation and focus order
2. **POS Terminal** — shortcut key assignments
3. **POS Interface Customization** — theme and font settings

---

## 3. Technical Documentation

### 3.1 Packages & Plugins
| Package | Purpose |
|---|---|
| **flutter** (built-in Semantics) | Screen reader labels, focus management |
| **flutter/services** (RawKeyboardListener) | Keyboard shortcut handling |
| **audioplayers** | Audio feedback sounds |

### 3.2 Technologies
- **Flutter Semantics** — built-in widget tree annotation for screen readers; `Semantics()` widget wraps interactive elements
- **FocusNode / FocusScope** — Flutter's focus management system for keyboard navigation
- **Windows Narrator** — native Windows screen reader; Flutter Desktop supports via UI Automation bridge
- **NVDA** — popular free screen reader for Windows; compatible with Flutter Desktop
- **MediaQuery.textScaleFactor** — Flutter's built-in text scaling mechanism
- **Custom theme system** — high-contrast themes defined with explicit foreground/background contrast ratios ≥ 4.5:1

---

## 4. Screens

### 4.1 Accessibility Settings Screen
| Field | Detail |
|---|---|
| **Route** | `/settings/accessibility` |
| **Purpose** | Configure accessibility preferences |
| **Sections** | **Visual:** Font size slider (80%-150%), High contrast toggle, Color-blind mode toggle, Reduced motion toggle. **Audio:** Audio feedback toggle, Volume slider. **Keyboard:** View/edit keyboard shortcuts, Reset to defaults |
| **Preview** | Live preview of font size and contrast changes |
| **Access** | All users (personal preference) |

### 4.2 Keyboard Shortcut Reference (Overlay)
| Field | Detail |
|---|---|
| **Route** | `Ctrl+/` or `F1` shortcut |
| **Purpose** | Quick reference of all keyboard shortcuts |
| **Layout** | Grouped by context: POS (F1-F12 + Ctrl combos), Navigation (Alt+1-9 for screens), General (Ctrl+Z undo, Ctrl+S save) |
| **Customization** | Click a shortcut to re-assign it |

---

## 5. APIs

### 5.1 Laravel Backend REST Endpoints
| Endpoint | Method | Purpose | Auth |
|---|---|---|---|
| `GET /api/staff/{id}/accessibility-prefs` | GET | Get saved accessibility preferences | Bearer token |
| `PUT /api/staff/{id}/accessibility-prefs` | PUT | Save accessibility preferences | Bearer token |

### 5.2 Flutter-Side Services
| Service Class | Purpose |
|---|---|
| `AccessibilityService` | Central state for all accessibility preferences; applies to app theme |
| `KeyboardShortcutService` | Registers and manages keyboard shortcuts; handles conflicts |
| `FocusManagementService` | Manages focus order; ensures logical tab order on complex screens |
| `AudioFeedbackService` | Plays sounds for POS actions when audio feedback is enabled |
| `HighContrastThemeService` | Provides high-contrast theme data; ensures 4.5:1 contrast ratio |

---

## 6. Full Database Schema

> **Note:** Accessibility preferences are stored as part of the user's settings — no dedicated tables. Preferences are stored in the existing `staff_users` table's `preferences_json` column or a separate key-value store.

### 6.1 Tables

No dedicated tables. Accessibility preferences stored as:

```json
// In staff_users preferences or local key-value store:
{
  "font_scale": 1.0,
  "high_contrast": false,
  "color_blind_mode": false,
  "reduced_motion": false,
  "audio_feedback": true,
  "audio_volume": 0.7,
  "custom_shortcuts": {
    "new_sale": "F2",
    "pay": "F5",
    "void_item": "F8",
    "open_drawer": "F10"
  }
}
```

### 6.2 Default Keyboard Shortcuts Reference

| Shortcut | Action | Context |
|---|---|---|
| `F1` | Help / Shortcut reference | Global |
| `F2` | New Sale | POS |
| `F3` | Search Product | POS |
| `F4` | Hold Cart | POS |
| `F5` | Pay / Checkout | POS |
| `F6` | Apply Discount | POS |
| `F7` | Customer Lookup | POS |
| `F8` | Void Last Item | POS |
| `F9` | Recall Held Cart | POS |
| `F10` | Open Cash Drawer | POS |
| `F11` | Print Last Receipt | POS |
| `F12` | Manager Override (PIN) | POS |
| `Ctrl+Z` | Undo Last Action | POS |
| `Ctrl+L` | Lock Screen | Global |
| `Alt+1-9` | Navigate to screen 1-9 | Global |
| `Esc` | Cancel / Close Dialog | Global |
| `Enter` | Confirm / Submit | Global |
| `Tab` | Next field / button | Global |
| `Shift+Tab` | Previous field / button | Global |

---

## 7. Business Rules

1. **Keyboard-first design** — every interactive element must be reachable via keyboard Tab navigation; focus order follows visual layout (left-to-right, top-to-bottom; or right-to-left in RTL)
2. **No shortcut conflicts** — custom shortcut assignments are validated; system-level shortcuts (Ctrl+C, Ctrl+V) cannot be overridden
3. **Font scale bounds** — font scaling is limited to 80%-150% to prevent layout overflow; at 150%, all screens must remain fully functional
4. **High contrast minimum** — high contrast mode ensures all text has a contrast ratio ≥ 7:1 against its background (WCAG AAA for text)
5. **Color-blind indicators** — all UI elements that use color to convey meaning (e.g., red = error, green = success) must also use an icon or shape differentiator
6. **Audio feedback default** — audio feedback is enabled by default for POS actions (scan beep, payment chime); can be disabled per user
7. **Focus visible** — keyboard focus indicator (ring/outline) is always visible and has high contrast against the background
8. **Screen reader labels** — every button, input, and status indicator has a descriptive `Semantics` label in the active language
9. **Reduced motion** — when enabled, all animations are replaced with instant transitions; no parallax, no slide-in effects
10. **Per-user persistence** — accessibility preferences are tied to the staff user; when a user clocks in, their preferences are automatically applied
