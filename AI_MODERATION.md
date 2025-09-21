# AI Moderation System

This Chirper application now includes an AI-powered content moderation system using Laravel's queue system for asynchronous processing.

## Features

- **Asynchronous Moderation**: All chirps are automatically queued for AI moderation after creation or update
- **Fallback System**: Uses rule-based moderation when AI services are unavailable
- **Status Tracking**: Chirps have moderation status (pending, approved, rejected) with timestamps
- **User Experience**: Only approved chirps are displayed publicly
- **Comprehensive Testing**: Full test coverage for all moderation functionality

## How It Works

1. **Chirp Creation/Update**: When a user creates or updates a chirp, it's automatically queued for moderation
2. **AI Processing**: The `ModerateChirp` job processes the content using the `AIModerationService`
3. **Status Update**: The chirp's moderation status is updated based on the AI analysis
4. **Public Display**: Only approved chirps are shown on the main feed

## AI Moderation Service

The `AIModerationService` supports:

- **OpenAI Integration**: Can use OpenAI's moderation API when configured
- **Fallback Rules**: Basic content filtering when AI services are unavailable
- **Content Analysis**: Checks for:
  - Inappropriate language
  - Excessive capitalization (spam detection)
  - Word repetition (spam detection)

## Configuration

Add your OpenAI API key to your `.env` file:

```env
OPENAI_API_KEY=your_api_key_here
```

If no API key is provided, the system will use fallback rule-based moderation.

## Queue Processing

To process moderation jobs, run the queue worker:

```bash
php artisan queue:work
```

## Database Schema

The system adds these fields to the `chirps` table:

- `moderation_status`: enum('pending', 'approved', 'rejected')
- `moderation_reason`: text (reason for approval/rejection)
- `moderated_at`: timestamp (when moderation was completed)

## Testing

Run the moderation tests:

```bash
php artisan test tests/Feature/ModerationTest.php
```

## Usage Examples

### Creating a Chirp
```php
$chirp = auth()->user()->chirps()->create(['message' => 'Hello world!']);
ModerateChirp::dispatch($chirp); // Automatically dispatched in controller
```

### Checking Moderation Status
```php
$chirp = Chirp::find(1);

if ($chirp->isApproved()) {
    // Chirp is approved and visible
}

if ($chirp->isPending()) {
    // Chirp is still being reviewed
}

if ($chirp->isRejected()) {
    // Chirp was rejected
}
```

### Querying by Status
```php
$approvedChirps = Chirp::approved()->get();
$pendingChirps = Chirp::pending()->get();
$rejectedChirps = Chirp::rejected()->get();
```

## Extending the System

The moderation system is designed to be easily extensible:

1. **Add New AI Providers**: Extend `AIModerationService` to support other AI services
2. **Custom Rules**: Add more sophisticated content analysis rules
3. **Human Review**: Integrate with human moderation workflows
4. **Notifications**: Send notifications when chirps are rejected
5. **Analytics**: Track moderation statistics and patterns

## Security Considerations

- Failed moderation jobs default to approval to avoid blocking legitimate content
- All moderation decisions are logged for audit purposes
- The system gracefully handles API failures and timeouts
- Sensitive content is not stored in logs (only previews)
