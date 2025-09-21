# Gemini API Integration & Rate Limiting

This document describes the new features added to the Chirper application: Gemini API integration for AI moderation and rate limiting for chirp creation.

## 🚀 Gemini API Integration

### Overview
The AI moderation system now uses Google's Gemini API instead of OpenAI, providing free AI-powered content moderation.

### Configuration
Add your Gemini API key to your `.env` file:

```env
GEMINI_API_KEY=your_gemini_api_key_here
```

### How It Works
1. **API Integration**: Uses Gemini 2.5 Flash-Lite model for content analysis
2. **Safety Filters**: Built-in safety settings for harassment, hate speech, explicit content, and dangerous content
3. **Smart Prompting**: Sends content to Gemini with specific instructions for moderation
4. **Fallback System**: Falls back to rule-based moderation if API fails

### API Response Processing
- **SAFE Response**: Content is approved
- **UNSAFE Response**: Content is rejected with reason
- **Safety Filters**: Content blocked by Gemini's safety system is automatically rejected
- **API Failures**: Gracefully falls back to basic moderation rules

## 🛡️ Rate Limiting

### Overview
Users are limited to 10 chirps per hour to prevent spam and abuse.

### How It Works
1. **Per-User Limits**: Each authenticated user has their own rate limit
2. **Cache-Based**: Uses Laravel's cache system for efficient tracking
3. **Time-Based**: Limits reset every hour
4. **Informative Messages**: Users see exactly how long they need to wait

### Rate Limit Details
- **Limit**: 10 chirps per hour per user
- **Scope**: Applies to both creating new chirps and updating existing ones
- **Guests**: Not affected (redirected to login instead)
- **Reset**: Automatic after 1 hour

### User Experience
When users hit the rate limit, they see a clear message:
```
"You've reached the limit of 10 chirps per hour. Please wait X minutes before posting again."
```

## 🔧 Implementation Details

### Middleware
- **RateLimitChirps**: Custom middleware that checks and enforces rate limits
- **Applied to**: POST `/chirps` and PUT `/chirps/{chirp}` routes
- **Cache Keys**: `chirp_rate_limit_{user_id}` and `chirp_rate_limit_reset_{user_id}`

### Service Updates
- **AIModerationService**: Updated to use Gemini API with proper error handling
- **Configuration**: Added Gemini API settings to `config/services.php`
- **Fallback**: Maintains backward compatibility with rule-based moderation

### UI Updates
- **Error Display**: Added error alert component to show rate limit messages
- **User Feedback**: Clear, informative error messages with wait times

## 🧪 Testing

### Test Coverage
- **Gemini API Integration**: Tests for API responses, safety filters, and fallbacks
- **Rate Limiting**: Tests for limit enforcement, reset functionality, and user messages
- **Edge Cases**: API failures, guest users, and time-based resets

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test tests/Feature/GeminiModerationTest.php
```

## 🚀 Usage Examples

### Setting Up Gemini API
1. Get a free API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Add it to your `.env` file:
   ```env
   GEMINI_API_KEY=your_actual_api_key_here
   ```
3. The system will automatically use Gemini for moderation

### Rate Limiting in Action
```php
// Users can create up to 10 chirps per hour
$user = User::find(1);

// After 10 chirps, they'll see:
// "You've reached the limit of 10 chirps per hour. Please wait 45 minutes before posting again."
```

### Monitoring Rate Limits
```php
// Check current rate limit status
$userId = 1;
$currentCount = Cache::get("chirp_rate_limit_{$userId}", 0);
$resetTime = Cache::get("chirp_rate_limit_reset_{$userId}");

echo "User has created {$currentCount} chirps this hour";
echo "Limit resets at: " . date('Y-m-d H:i:s', $resetTime);
```

## 🔒 Security & Performance

### Security Features
- **API Key Protection**: Gemini API key stored securely in environment variables
- **Rate Limiting**: Prevents spam and abuse
- **Input Validation**: All content validated before processing
- **Error Handling**: Graceful degradation on API failures

### Performance Optimizations
- **Cache-Based Rate Limiting**: Fast, efficient rate limit tracking
- **Async Processing**: Moderation happens in background via queues
- **Fallback System**: No single point of failure
- **Minimal API Calls**: Efficient use of Gemini API quota

## 📊 Monitoring & Analytics

### Logging
- **API Calls**: All Gemini API requests logged
- **Rate Limits**: Rate limit hits logged for monitoring
- **Errors**: API failures and fallbacks logged
- **Moderation Results**: All moderation decisions logged

### Metrics to Track
- **API Usage**: Gemini API call volume and success rate
- **Rate Limit Hits**: How often users hit rate limits
- **Moderation Accuracy**: Approval/rejection rates
- **Fallback Usage**: How often fallback moderation is used

## 🎯 Benefits

### For Users
- **Free AI Moderation**: No cost for content moderation
- **Clear Feedback**: Informative error messages
- **Fair Usage**: Prevents spam while allowing normal usage
- **Fast Response**: Immediate feedback on rate limits

### For Administrators
- **Cost Effective**: Free Gemini API vs paid OpenAI
- **Abuse Prevention**: Rate limiting prevents spam
- **Reliable**: Fallback system ensures moderation always works
- **Scalable**: Cache-based rate limiting scales well

## 🔮 Future Enhancements

### Potential Improvements
- **Dynamic Rate Limits**: Adjust limits based on user behavior
- **Admin Override**: Allow admins to bypass rate limits
- **Analytics Dashboard**: Visual monitoring of rate limits and moderation
- **Custom Limits**: Different limits for different user types
- **Gemini Pro**: Upgrade to more powerful Gemini models for better accuracy
