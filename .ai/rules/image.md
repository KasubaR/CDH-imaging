---
paths:
  - 'app/Http/Controllers/Image/**,app/Http/Requests/Image/**'
---

# Image

## Image uploads must verify real content, never trust the client
Never trust an uploaded file's filename or client-declared Content-Type. StoreImagesRequest's mimes/mimetypes/image rules are a first pass but rely on Laravel's UploadedFile::getMimeType()/guessExtension(), which (in real, non-test requests) content-sniffs via finfo — still not proof the bytes decode as an image. `ImageIngestionService::verifyGenuineImage()` (private method) is the real gate — used by every upload path, see .ai/rules/requests-image.md: it independently re-derives MIME type via finfo_file() and confirms decodability via getimagesize() on the file's real path, requires both to agree, and only then returns the extension to store under (plus the sha256 checksum — see the storage/dedup rule). Storage filenames must always come from that verified extension + Str::uuid() (the `uuid`/`stored_filename` columns) — never from getClientOriginalName() or getClientOriginalExtension(). If you touch this flow, keep verifyGenuineImage() as the single source of truth for what gets persisted; don't reintroduce client-derived filenames/extensions/mime types into the storage path or the images.mime_type column.

Testing note: Illuminate\Http\Testing\File overrides getMimeType() to return a client-declared/extension-guessed value (never real content-sniffed), so PHPUnit tests using UploadedFile::fake() cannot exercise Laravel's built-in mimes/mimetypes rules' real-world content-sniffing — only verifyGenuineImage()'s raw finfo_file()/getimagesize() calls on the real temp path are meaningfully testable. See tests/Feature/ImageUploadTest.php::test_non_image_content_disguised_with_an_image_extension_is_rejected for the pattern.
