# Biometric Authentication API Documentation

## Overview
The Biometric Authentication API allows users to enroll and authenticate using biometric data such as fingerprints, face recognition, or voice recognition. This provides a more secure and convenient authentication method for mobile applications.

## Authentication Flow

### 1. User Registration/Login
- User must first create an account and login using traditional email/password
- User receives authentication token

### 2. Biometric Enrollment
- User enrolls their biometric data (fingerprint, face, etc.)
- Biometric template is hashed and stored securely
- Device ID is associated with the enrollment

### 3. Biometric Authentication
- User can now login using their biometric data
- System verifies the biometric template against stored hash
- Returns authentication token on success

## API Endpoints

### 1. Enroll Biometric Data
**POST** `/api/auth/biometric/enroll`

**Headers:**
```
Authorization: Bearer {your-token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "biometric_data": {
        "template": "base64-encoded-biometric-template",
        "type": "fingerprint", // fingerprint, face, voice
        "version": "1.0"
    },
    "device_id": "unique-device-identifier",
    "device_name": "iPhone 15 Pro",
    "device_model": "iPhone15,2"
}
```

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Biometric enrollment successful",
    "data": {
        "biometric_enabled": true,
        "enrolled_at": "2025-01-20T10:30:00.000000Z",
        "biometric_type": "fingerprint"
    }
}
```

**Response (Error - 409):**
```json
{
    "status": false,
    "message": "Biometric authentication is already enabled for this account",
    "errors": ["Biometric already enrolled"]
}
```

### 2. Authenticate with Biometric
**POST** `/api/auth/biometric/authenticate`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "email": "user@example.com",
    "biometric_data": {
        "template": "base64-encoded-biometric-template",
        "type": "fingerprint"
    },
    "device_id": "unique-device-identifier"
}
```

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Authentication successful",
    "data": {
        "user": {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "user@example.com",
            "role": "user",
            "account_status": "active",
            "profile_completed": true,
            "biometric_enabled": true
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
    }
}
```

**Response (Error - 401):**
```json
{
    "status": false,
    "message": "Biometric authentication failed",
    "errors": ["Biometric verification failed"]
}
```

### 3. Get Biometric Status
**GET** `/api/auth/biometric/status`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Biometric status retrieved",
    "data": {
        "biometric_enabled": true,
        "enrolled_at": "2025-01-20T10:30:00.000000Z",
        "biometric_type": "fingerprint",
        "device_registered": true
    }
}
```

### 4. Disable Biometric Authentication
**DELETE** `/api/auth/biometric/disable`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Biometric authentication disabled successfully",
    "data": {
        "biometric_enabled": false
    }
}
```

### 5. Re-enroll Biometric Data
**PUT** `/api/auth/biometric/reenroll`

**Headers:**
```
Authorization: Bearer {your-token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "biometric_data": {
        "template": "new-base64-encoded-biometric-template",
        "type": "face",
        "version": "2.0"
    },
    "device_id": "unique-device-identifier"
}
```

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Biometric enrollment successful",
    "data": {
        "biometric_enabled": true,
        "enrolled_at": "2025-01-20T11:45:00.000000Z",
        "biometric_type": "face"
    }
}
```

### 6. Get Authentication Attempts
**GET** `/api/auth/biometric/attempts?hours=24`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Query Parameters:**
- `hours` (optional): Number of hours to look back (default: 24, max: 168)

**Response (Success - 200):**
```json
{
    "status": true,
    "message": "Authentication attempts retrieved",
    "data": {
        "user_id": 1,
        "biometric_enabled": true,
        "last_enrolled": "2025-01-20T10:30:00.000000Z",
        "message": "Detailed attempt tracking requires additional implementation"
    }
}
```

## Security Features

### 1. Biometric Data Protection
- Biometric templates are hashed using Laravel's Hash facade
- Raw biometric data is never stored in the database
- Only hashed templates are stored for verification

### 2. Device Association
- Each biometric enrollment is tied to a specific device ID
- Users can only authenticate from the enrolled device
- Provides additional security layer

### 3. Token Management
- Biometric authentication generates 30-day access tokens
- Tokens are automatically revoked when biometric is disabled
- Separate token scope for biometric authentication

### 4. Audit Logging
- All biometric operations are logged for security monitoring
- Failed authentication attempts are logged with details
- Enrollment and enrollment changes are tracked

## Error Handling

### Common Error Responses

**401 Unauthorized:**
```json
{
    "status": false,
    "message": "User not authenticated"
}
```

**404 Not Found:**
```json
{
    "status": false,
    "message": "Biometric authentication not available for this account",
    "errors": ["User not found or biometric not enrolled"]
}
```

**403 Forbidden:**
```json
{
    "status": false,
    "message": "Account is not active",
    "errors": ["Your account status is: suspended"]
}
```

**422 Validation Error:**
```json
{
    "status": false,
    "message": "The given data was invalid",
    "errors": {
        "biometric_data.template": ["The biometric template is required."],
        "device_id": ["The device id field is required."]
    }
}
```

**500 Internal Server Error:**
```json
{
    "status": false,
    "message": "Biometric enrollment failed",
    "errors": ["Database connection error"]
}
```

## Mobile Implementation Guidelines

### 1. Biometric Template Generation
- Use device-specific biometric APIs (Touch ID, Face ID, Fingerprint API)
- Generate templates in secure enclave when available
- Encode templates as base64 for transmission

### 2. Device ID Generation
- Use unique device identifiers (UUID, IMEI, etc.)
- Ensure identifier is consistent across app reinstalls
- Consider privacy implications of device identification

### 3. Security Best Practices
- Implement biometric authentication fallback
- Store authentication tokens securely (Keychain, KeyStore)
- Implement proper error handling for biometric failures
- Validate biometric availability before attempting enrollment

### 4. User Experience
- Provide clear enrollment instructions
- Handle biometric unavailability gracefully
- Offer traditional login as fallback option
- Show biometric status in app settings

## Testing

### Test Scenarios
1. **Enrollment**: Test successful enrollment and duplicate enrollment prevention
2. **Authentication**: Test successful and failed biometric authentication
3. **Device Mismatch**: Test authentication from different device
4. **Disabled Biometric**: Test behavior when biometric is disabled
5. **Account Status**: Test authentication with inactive accounts
6. **Token Management**: Test token generation and revocation

### Test Data
```json
{
    "test_biometric_template": "dGVzdC1iaW9tZXRyaWMtdGVtcGxhdGU=",
    "test_device_id": "test-device-12345",
    "test_email": "test@example.com"
}
```