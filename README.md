# Social Media API

This microservice provides endpoints to publish posts to Meta platforms (Facebook and Instagram) using the Meta Graph API. It integrates with the core-domain package for data persistence.

Base route prefix: `/api/v1/marketing/socialmedia`

All endpoints require authentication via Bearer token (`Authorization: Bearer <token>`).

## Environment Variables

- `META_PAGE_ACCESS_TOKEN`: Access token for Meta API (used for Facebook and Instagram if not provided in request).
- `META_PAGE_ID`: Facebook Page ID (optional, can be provided in request).
- `META_IG_USER_ID`: Instagram User ID (optional, can be provided in request).
- `META_IG_ACCESS_TOKEN`: Instagram Access Token (optional, uses page token if not provided).

## Endpoints

### Publish to Facebook

- **Method**: POST
- **URL**: `/api/v1/marketing/socialmedia/posts/facebook`
- **Parameters** (form-data or x-www-form-urlencoded):
  - `message` (optional, string): Text content of the post (required if no image_url).
  - `link` (optional, string): URL to include in the post.
  - `image_url` (optional, string): URL of image to attach.
  - `image` (optional, file): Image file to upload.
  - `video` (optional, file): Video file to upload.
  - `page_id` (optional, string): Facebook Page ID.
  - `access_token` (optional, string): Meta access token.
  - `campaign_id` (optional, integer): Associate post with this campaign.
- **Purpose**: Publishes a post (text, image, or video) to a Facebook Page and saves it to the database.

  ```bash
  curl -X POST "http://localhost:8000/api/v1/marketing/socialmedia/posts/facebook" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "message=Hello World&campaign_id=1"
  ```

### Publish to Instagram

- **Method**: POST
- **URL**: `/api/v1/marketing/socialmedia/posts/instagram`
- **Parameters** (form-data or x-www-form-urlencoded):
  - `image_url` (required, string): URL of image to post.
  - `caption` (optional, string): Caption for the image.
  - `image` (optional, file): Image file to upload (alternative to image_url).
  - `ig_user_id` (optional, string): Instagram User ID.
  - `access_token` (optional, string): Meta access token.
  - `campaign_id` (optional, integer): Associate post with this campaign.
- **Purpose**: Publishes an image to Instagram and saves it to the database.

  ```bash
  curl -X POST "http://localhost:8000/api/v1/marketing/socialmedia/posts/instagram" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "image_url=https://example.com/image.jpg&caption=My Caption&campaign_id=1"
  ```

## Notes

- Posts are saved to the database with associations to campaigns if `campaign_id` is provided.
- If `campaign_id` is invalid, returns error `{"error": "Campaign not found", "code": "CAMPAIGN_NOT_FOUND"}`.
- Requires valid Meta API tokens for publishing.
- Supports text, image, and video posts for Facebook; images for Instagram.
