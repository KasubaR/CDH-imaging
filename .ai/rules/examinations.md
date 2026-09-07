---
paths:
  - 'resources/css/pages/viewer.css,resources/js/viewer.js,resources/views/examinations/show.blade.php'
---

# Examinations

## Viewer: toolbar sits above the image; percentage-height trap in .viewer-canvas
The viewer toolbar (zoom/rotate/brightness/contrast/invert/measure/compare/fullscreen/reset/download/print) renders as ONE row directly above .viewer-canvas — this was a deliberate placement choice (not below), don't move it back below the image.

CSS trap already hit once: .viewer-canvas uses `display:flex; align-items:center` (for centering fallback content), which means a child given `height:100%` has no definite reference height to resolve against and collapses to 0 — this silently zeroed out the whole pane/viewport chain. Fix in place: .viewer-stage uses `position:absolute; inset:0` against .viewer-canvas's `position:relative`, not `height:100%`. If you add new children under .viewer-canvas that need to fill it, use absolute+inset, not percentage heights.

Pane/tool state (zoom, rotate, pan, brightness, contrast, invert, measurement) lives only in resources/js/viewer.js's in-memory `panes` object — it is intentionally not persisted anywhere (no DB column, resets on reload). Measurement is labeled in pixels, not mm/cm — there's no DICOM pixel-spacing calibration for plain JPEG/PNG uploads, so don't add a real-world unit without first adding real calibration data.
