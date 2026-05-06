---
name: Modern-Traditional Cameroon
colors:
  surface: '#fcf9f8'
  surface-dim: '#dcd9d9'
  surface-bright: '#fcf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f2'
  surface-container: '#f0eded'
  surface-container-high: '#eae7e7'
  surface-container-highest: '#e4e2e1'
  on-surface: '#1b1c1c'
  on-surface-variant: '#54433c'
  inverse-surface: '#303030'
  inverse-on-surface: '#f3f0ef'
  outline: '#87736b'
  outline-variant: '#dac1b8'
  surface-tint: '#944925'
  primary: '#823b18'
  on-primary: '#ffffff'
  primary-container: '#a0522d'
  on-primary-container: '#ffe1d6'
  inverse-primary: '#ffb596'
  secondary: '#7a5900'
  on-secondary: '#ffffff'
  secondary-container: '#fdbc13'
  on-secondary-container: '#6b4d00'
  tertiary: '#185946'
  on-tertiary: '#ffffff'
  tertiary-container: '#35725d'
  on-tertiary-container: '#b4f4da'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdbcd'
  primary-fixed-dim: '#ffb596'
  on-primary-fixed: '#360f00'
  on-primary-fixed-variant: '#76320f'
  secondary-fixed: '#ffdea3'
  secondary-fixed-dim: '#fdbc13'
  on-secondary-fixed: '#261900'
  on-secondary-fixed-variant: '#5d4200'
  tertiary-fixed: '#b0f0d6'
  tertiary-fixed-dim: '#95d3ba'
  on-tertiary-fixed: '#002117'
  on-tertiary-fixed-variant: '#0b513d'
  background: '#fcf9f8'
  on-background: '#1b1c1c'
  surface-variant: '#e4e2e1'
typography:
  display-lg:
    fontFamily: Newsreader
    fontSize: 48px
    fontWeight: '600'
    lineHeight: '1.1'
  headline-lg:
    fontFamily: Newsreader
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Newsreader
    fontSize: 24px
    fontWeight: '500'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Work Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Work Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Work Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 8px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 40px
---

## Brand & Style

This design system establishes a "Modern-Traditional" aesthetic, bridging Cameroon's rich cultural heritage with the clarity of contemporary educational technology. The brand personality is scholarly yet welcoming, positioning itself as a prestigious gateway to local wisdom and linguistic mastery.

The visual style is a refined mix of **Minimalism** and **Tactile** design. It prioritizes heavy whitespace to allow the complex patterns of Ndop and Toghu textiles to breathe without cluttering the interface. The emotional response should be one of "rooted innovation"—users should feel they are engaging with a professional institution that deeply respects human connection and ancestral knowledge.

## Colors

The palette is derived from natural pigments and traditional Cameroonian craftsmanship. 
- **Primary (Earthy Terracotta):** Used for key actions and branding elements, representing the clay and soil.
- **Secondary (Warm Yellow):** Used for highlights, achievements, and progress indicators, evoking the warmth of the sun and Toghu embroidery.
- **Tertiary (Deep Forest Green):** Used for success states and secondary navigational accents, representing the lush biodiversity of the South and West regions.
- **Neutral (Rich Charcoal):** Reserved for high-contrast typography to ensure maximum legibility.
- **Background (Canvas White):** A slightly warm, off-white base that prevents the "clinical" feel of pure white and supports the earthy tones.

## Typography

This design system utilizes a high-contrast typographic pairing to reinforce the "Modern-Traditional" narrative. 
- **Headings:** The serif typeface **Newsreader** provides an authoritative, literary feel, reminiscent of historical texts and formal cultural documentation.
- **Body & UI:** **Work Sans** provides a grounded, professional, and highly legible experience for long-form learning content and functional interface elements. 
- **Styling:** Use sentence case for headings to maintain a human and approachable tone.

## Layout & Spacing

The layout follows a **Fixed Grid** model on desktop to maintain elegant proportions and generous whitespace. A 12-column system is used for dashboard and learning modules, while a centered 8-column layout is preferred for reading-heavy cultural articles. 

Spacing is governed by an 8px rhythmic scale. Generous vertical margins (64px+) should be used between major sections to emphasize the "Minimalist" influence and prevent cognitive overload during the learning process.

## Elevation & Depth

To maintain a "Human" and "Professional" feel, this design system avoids aggressive shadows. Depth is communicated through:
- **Tonal Layers:** Using subtle shifts in background color (e.g., a slightly darker cream for sidebar containers).
- **Subtle Ambient Shadows:** Surfaces that require interaction (like cards) use extremely soft, diffused shadows with a slight Terracotta tint (`rgba(160, 82, 45, 0.08)`) to make them feel integrated into the earthy palette rather than floating in a void.
- **Flat Accents:** Use 1px solid borders in a lightened neutral shade for input fields and containers to maintain a clean, organized look.

## Shapes

The shape language is "Soft" (0.25rem - 0.75rem), striking a balance between the precision of modern UI and the organic nature of traditional crafts. 
- **Buttons and Inputs:** Use a 4px (0.25rem) radius for a professional, slightly structured feel.
- **Cards and Modals:** Use an 8px (0.5rem) radius to soften the larger surfaces.
- **Decorative Patterns:** Geometric textile patterns (Ndop/Toghu) should be used as background masks or subtle borders rather than foreground elements. These should be strictly geometric—circles, triangles, and cowrie shapes.

## Components

- **Navigation:** The top bar features a dedicated **Brand Logo Placeholder** on the far left. The navigation links use `label-md` for a clean, professional appearance.
- **Buttons:** Primary buttons are solid Terracotta with white text. Secondary buttons use a Deep Forest Green outline. Both utilize the "Soft" radius.
- **Cards:** Content cards use the "Canvas White" background with a 1px border. A subtle geometric Ndop pattern should be applied to the card header or as a 4px bottom decorative strip.
- **Input Fields:** Clean, understated borders that thicken slightly on focus using the Terracotta color.
- **Progress Indicators:** Use the Warm Yellow for progress bars and achievement badges to symbolize growth and energy.
- **Pattern Overlays:** Use low-opacity (5-10%) SVG patterns of Toghu geometric shapes in the background of page sections to provide texture without sacrificing readability.