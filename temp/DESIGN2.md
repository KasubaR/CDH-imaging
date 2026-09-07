---
name: Clinical Precision
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#424752'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#727783'
  outline-variant: '#c2c6d4'
  surface-tint: '#005db6'
  primary: '#00478d'
  on-primary: '#ffffff'
  primary-container: '#005eb8'
  on-primary-container: '#c8daff'
  inverse-primary: '#a9c7ff'
  secondary: '#006a63'
  on-secondary: '#ffffff'
  secondary-container: '#79f3e7'
  on-secondary-container: '#006f67'
  tertiary: '#793100'
  on-tertiary: '#ffffff'
  tertiary-container: '#9f4300'
  on-tertiary-container: '#ffcfb9'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#a9c7ff'
  on-primary-fixed: '#001b3d'
  on-primary-fixed-variant: '#00468c'
  secondary-fixed: '#7cf6ea'
  secondary-fixed-dim: '#5ddacd'
  on-secondary-fixed: '#00201d'
  on-secondary-fixed-variant: '#00504a'
  tertiary-fixed: '#ffdbcb'
  tertiary-fixed-dim: '#ffb691'
  on-tertiary-fixed: '#341100'
  on-tertiary-fixed-variant: '#793100'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  data-mono:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  gutter: 24px
  margin-desktop: 40px
  margin-mobile: 16px
  container-max: 1440px
---

## Brand & Style
The design system is engineered for the high-stakes environment of Chililabombwe District Hospital. The brand personality is rooted in **Clinical Modernism**—a style that prioritizes clarity, hygiene, and efficiency above all else. It draws heavily from **Minimalism** to reduce cognitive load for medical staff, while utilizing a **Corporate/Modern** framework to ensure reliability and trust.

The emotional response should be one of "calm authority." By using expansive whitespace and a structured grid, the UI helps practitioners focus on patient data without visual distraction. The aesthetic is "sterilized but human," avoiding unnecessary decoration in favor of functional precision.

## Colors
The palette is anchored by **Deep Hospital Blue**, establishing immediate authority and professional standards. **Medical Green** is used sparingly for care-related callouts and secondary actions to evoke a sense of health and recovery.

The background strategy utilizes a "Layered White" approach, where the base surface is pure white (#FFFFFF) and secondary containers use a very subtle light gray (#F8FAFC) to create soft boundaries without heavy borders. Semantic colors are optimized for high visibility against white backgrounds to ensure that alerts and errors are never missed in a fast-paced clinical setting.

## Typography
**Inter** is the sole typeface for this design system, chosen for its exceptional legibility and systematic "neutral" feel. It is configured with tabular figures (`tnum`) for data-heavy tables to ensure numerical alignment across patient records and vitals.

- **Headlines:** Use tight tracking and bold weights to establish a clear hierarchy.
- **Body:** Set with generous line height to improve readability of long-form medical notes.
- **Labels:** Small caps or uppercase are used for category headers to distinguish them from editable data.
- **Data Mono:** While using Inter, we apply specific OpenType features for numerical data to mimic the precision of a monospaced font without losing the brand's humanist touch.

## Layout & Spacing
The layout follows a **Fixed-Fluid Hybrid Grid**. On desktop, the main content area is capped at 1440px to prevent eye-strain on ultra-wide monitors common in diagnostic stations. 

- **The 8px Rhythm:** All spacing (padding, margins, gaps) must be multiples of 4px, with 8px and 16px being the primary increments.
- **Grid:** A 12-column grid is used for desktop dashboards. Medical cards usually span 3, 4, or 6 columns.
- **Density:** We utilize "Comfortable" density for patient intake forms and "Compact" density for data-heavy laboratory results or pharmacy inventory tables.
- **Mobile:** Elements reflow into a single column with a 16px side margin. High-priority "Action" buttons are pinned to the bottom of the viewport for easy thumb access.

## Elevation & Depth
In this design system, depth is communicated through **Low-contrast Outlines** and **Tonal Layers** rather than heavy shadows, maintaining a clean, sterile feel.

- **Level 0 (Surface):** Pure white background.
- **Level 1 (Cards):** 1px solid border (#E2E8F0) with a very soft, diffused 2px shadow to lift the card slightly from the background.
- **Level 2 (Modals/Dropdowns):** A crisp 1px border with a medium-diffusion shadow (8px blur, 4% opacity) to indicate temporary overlay.
- **Interactive States:** On hover, cards transition to a slightly thicker border in Primary Blue rather than increasing shadow depth, keeping the interface feeling "flat" and efficient.

## Shapes
We use **Soft (0.25rem)** roundedness. This subtle rounding removes the aggressive "sharpness" of medical software while maintaining a professional, structured architectural feel. 

- **Standard Elements:** Buttons, inputs, and small cards use 4px (0.25rem).
- **Large Containers:** Dashboard widgets and main content areas use 8px (0.5rem).
- **Status Badges:** These are the only exception and use a **Pill-shaped** (100px) radius to distinguish them clearly from interactive buttons.

## Components
- **Buttons:** Primary buttons are solid Deep Hospital Blue with white text. Secondary buttons use a teal outline. All buttons have a minimum height of 44px to meet accessibility standards for "fat-finger" interactions on touch tablets.
- **Cards:** Data segments are housed in cards with a 1px #E2E8F0 border. Headers within cards have a subtle gray background (#F8FAFC) to separate metadata from the content.
- **Status Indicators:** 
    - *Viewed:* Ghost Blue (Light blue background, Deep blue text).
    - *Acknowledged:* Ghost Teal (Light teal background, Dark teal text).
    - *Sent:* Ghost Gray (Light gray background, Dark gray text).
- **Form Fields:** Inputs must have a persistent label above the field. Error states use a 2px red border and an icon for accessibility. 
- **File Uploads:** Drag-and-drop zones use a dashed Medical Green border with a centered cloud icon. Progress bars use a solid Medical Green fill to indicate "health" and completion.
- **Data Tables:** Row hovering should highlight the entire row in a soft blue tint (#F1F5F9) to help clinical staff track data horizontally across many columns.