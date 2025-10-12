# ShelterBaze App Flow Documentation

## Table of Contents
1. [Overview](#overview)
2. [User Roles](#user-roles)
3. [Authentication & Onboarding](#authentication--onboarding)
4. [Tenant Journey](#tenant-journey)
5. [Landlord Journey](#landlord-journey)
6. [Agent Journey](#agent-journey)
7. [Property Rental Process](#property-rental-process)
8. [Payment System](#payment-system)
9. [Wallet & Withdrawal System](#wallet--withdrawal-system)
10. [Communication & Support](#communication--support)
11. [Email Notification System](#email-notification-system)
12. [Biometric Authentication](#biometric-authentication)
13. [Automated Jobs & Scheduling](#automated-jobs--scheduling)
14. [Additional Features](#additional-features)

## Overview

ShelterBaze is a comprehensive property rental platform that connects tenants, landlords, and agents in Nigeria. The platform facilitates the entire rental process from property discovery to payment management, with advanced features like rent savings, property verification, biometric authentication, automated withdrawal processing, and comprehensive communication tools.

**Key Platform Features:**
- Multi-role user management (Tenants, Landlords, Agents, Admins)
- Property listing and verification system
- Integrated payment processing with multiple gateways
- Advanced wallet and withdrawal management
- Biometric authentication for enhanced security
- Automated job scheduling for agreement management
- Comprehensive email notification system
- Real-time chat and communication tools
- Analytics and insights dashboard

## User Roles

The platform supports four distinct user roles:

- **User/Tenant**: Individuals looking to rent properties
- **Landlord**: Property owners who list properties for rent
- **Agent**: Verified professionals who assist with property verification and landlord support
- **Admin**: Platform administrators with full system access

## Authentication & Onboarding

### 1. Registration Process

#### Step 1: Initial Registration
- **Endpoint**: `POST /api/auth/send-onboarding-otp`
- User provides basic information:
  - First name
  - Last name
  - Email address
  - Phone number
  - Password
  - Role selection (user/tenant, landlord, agent)

#### Step 2: OTP Verification
- **Endpoint**: `POST /api/auth/register`
- System sends OTP to phone number
- User verifies OTP to complete registration
- Account is created with `account_status: 'pending'`

#### Step 3: Email Verification
- Email verification link sent to user's email
- User clicks link to verify email
- `email_verified_at` timestamp is set

### 2. Profile Completion

#### For All Users:
- **Endpoint**: `POST /api/profile/complete-profile`
- Complete UserProfile with:
  - NIN (National Identification Number)
  - NIN selfie upload
  - Full address (state, LGA, specific address)
  - Additional verification documents

#### For Agents:
- Automatic agent ID generation (`AGT000001` format)
- Additional verification requirements
- Admin approval required for agent status

## Tenant Journey

### 1. Profile Setup
1. Complete registration and verification
2. Upload required documents (NIN, selfie)
3. Set preferred location and budget preferences
4. Account status changes to `active` after verification

### 2. Property Discovery
- **Endpoint**: `GET /api/listing/properties`
- Browse available properties with filters:
  - Property type (1-4 bedroom, studio, duplex, bungalow)
  - Location (state, LGA)
  - Price range
  - Facilities

### 3. Property Interest & Engagement
1. **View Property Details**
   - Property information, images, location
   - Landlord/agent contact information

2. **Pay Engagement Fee (Optional)**
   - **Endpoint**: `POST /api/engagement/initialize`
   - Pay small fee to access landlord contact details (currently disabled)
   - Payment processed through Paystack
   - **Verification**: `GET /api/engagement/verify/{reference}`

3. **Contact Landlord/Agent**
   - **Endpoint**: `GET /api/engagement/contact/{propertyId}`
   - **Update**: Contact details are now accessible without payment requirement
   - Direct access to landlord/agent information
   - Initiate communication through chat system

### 4. Property Viewing & Application
1. Schedule property viewing through chat
2. Submit rental application
3. Provide additional documentation if required

### 5. Rental Agreement
1. **Agreement Creation**
   - Landlord creates rental agreement
   - Terms and conditions specified
   - Rent amount and commission calculated

2. **Agreement Acceptance**
   - Tenant reviews and accepts agreement
   - Digital signature process
   - Agreement status becomes `active`

### 6. Rent Payment Process
1. **Generate Invoice**
   - **Endpoint**: `POST /api/rent/generate-invoice`
   - **Updates**: 
     - No longer requires engagement fee payment
     - Allows multiple rental agreements per user (max 5 active)
     - Automatically applies long-term rental discounts (5% for 12+ months)
     - Uses `updateOrCreate` to prevent duplicate agreements
   - System generates rental invoice based on period

2. **Payment Options**
   - **Online Payment**: Direct integration with payment gateways
   - **Offline Payment**: Bank transfer with proof upload
   - **Endpoint**: `POST /api/rent/upload-payment-proof`
   - **Enhancement**: Supports creating agreement during offline payment upload
   - **Validation**: 2% tolerance for payment amounts (accounting for bank charges)

3. **Payment Verification**
   - Admin verifies offline payments
   - Automatic verification for online payments
   - **Status tracking**: `GET /api/rent/payment-history`
   - Enhanced filtering by status, property, and date ranges

## Landlord Journey

### 1. Profile Setup
1. Complete registration as landlord
2. Provide property ownership documents
3. Account verification by admin
4. Profile completion with business details

### 2. Property Listing
1. **Create Property Listing**
   - **Endpoint**: `POST /api/listing/properties`
   - Property details:
     - Title and description
     - Property type and facilities
     - Location (address, state, LGA, coordinates)
     - Rent amount (system calculates commission)

2. **Upload Media**
   - **Endpoint**: `POST /api/listing/upload-media/{id}`
   - Upload property images and videos
   - Set primary image

3. **Property Verification**
   - Agent assigned for property verification
   - Physical inspection conducted
   - Property status: `pending` → `verified` → `open`

### 3. Tenant Management
1. **View Interested Tenants**
   - **Endpoint**: `GET /api/engagement/interested-tenants/{propertyId}`
   - See tenants who paid engagement fee
   - Review tenant profiles

2. **Communication**
   - Chat with potential tenants
   - Schedule property viewings
   - Answer tenant inquiries

3. **Tenant Selection**
   - Review applications
   - Select preferred tenant
   - Create rental agreement

### 4. Rental Management
1. **Agreement Management**
   - Create and manage rental agreements
   - Set terms and conditions
   - Monitor agreement status

2. **Payment Tracking**
   - **Endpoint**: `GET /api/rent/payment-summary`
   - Track rent payments
   - View payment history
   - Generate payment receipts

3. **Property Status Management**
   - **Endpoint**: `POST /api/listing/toggle-status/{id}`
   - Open/close property for new tenants
   - Update property information

## Agent Journey

### 1. Agent Onboarding
1. Register with agent role
2. Complete enhanced verification
3. Admin approval for agent status
4. Receive unique agent ID

### 2. Property Verification
1. **Assignment Notification**
   - **Endpoint**: `GET /api/agents/assigned-properties`
   - Receive property verification assignments

2. **Verification Process**
   - **Endpoint**: `POST /api/agents/verify-property`
   - Conduct physical property inspection
   - Verify property details and documentation
   - Upload verification report and photos

3. **Status Updates**
   - Update property verification status
   - Provide verification comments
   - Submit final verification report

### 3. Landlord Support
1. **Assignment Management**
   - Receive landlord support assignments
   - Assist landlords with property listing
   - Guide through platform features

2. **Ongoing Support**
   - Answer landlord queries
   - Assist with tenant communication
   - Help resolve issues

## Property Rental Process

### Complete Flow: From Listing to Rental

1. **Property Listing** (Landlord)
   - Create property listing
   - Upload media and details
   - Submit for verification

2. **Property Verification** (Agent)
   - Agent assigned for verification
   - Physical inspection conducted
   - Property approved/rejected

3. **Property Discovery** (Tenant)
   - Browse verified properties
   - Apply filters and search
   - View property details

4. **Engagement (Optional)**
   - **Update**: Engagement fee requirement has been temporarily disabled
   - Contact details are now freely accessible
   - Direct communication with landlords/agents

5. **Communication & Viewing**
   - Chat between tenant and landlord
   - Schedule property viewing
   - Negotiate terms

6. **Application & Agreement**
   - Tenant submits application
   - Landlord reviews and selects tenant
   - Rental agreement created

7. **Payment Setup**
   - Agreement terms finalized
   - Payment schedule established
   - Bank details provided

8. **Ongoing Management**
   - Monthly rent payments
   - Issue reporting and resolution
   - Agreement renewal process

## Payment System

### 1. Engagement Fee Payment
- **Purpose**: Access landlord contact information
- **Amount**: Fixed small fee
- **Method**: Paystack integration
- **Verification**: Automatic
- **Status**: Currently disabled - contact details are freely accessible

### 2. Rent Payment Options

#### Online Payment
- Direct payment through integrated gateways
- Automatic verification
- Instant confirmation

#### Offline Payment
- Bank transfer to Shelterbaze account
- Upload payment proof with receipt/screenshot
- **Enhanced Features**:
  - Can create rental agreement during payment upload process
  - Payment amount validation with 2% tolerance for bank charges
  - Support for both existing and new rental agreements
  - Admin verification required within 24-48 hours
- **Endpoints**:
  - `GET /api/rent/bank-details`
  - `POST /api/rent/upload-payment-proof`

### 3. Payment Features
- **Payment History**: `GET /api/rent/payment-history`
- **Payment Receipts**: `GET /api/rent/payment-receipt/{id}`
- **Payment Reminders**: Automatic notifications
- **Late Payment Tracking**: Overdue payment management

### 4. Enhanced Rental Features
- **Long-term Rental Discounts**: 5% discount for rentals 12+ months
- **Multiple Property Rentals**: Users can have up to 5 active rental agreements
- **Flexible Payment Processing**: 
  - Support for both online and offline payments
  - Payment tolerance of ±2% for bank charges
  - Automatic agreement creation during payment upload
- **Renewal System**: 
  - Early renewal requests (within 90 days of expiry)
  - 5% renewal discount for returning tenants
  - Automated renewal notifications
- **Payment Summary & Analytics**: 
  - Comprehensive payment tracking
  - Monthly payment analytics
  - Property performance insights

## Wallet & Withdrawal System

### 1. Digital Wallet Management
- **Purpose**: Centralized fund management for all platform transactions
- **Features**:
  - Real-time balance tracking
  - Transaction history
  - Multi-currency support (NGN primary)
  - Secure fund storage

### 2. Withdrawal Process

#### Withdrawal Request Submission
- **Endpoints**: 
  - `POST /api/wallet/withdraw` - Submit withdrawal request
  - `GET /api/wallet/withdrawals` - View withdrawal history
  
- **Required Information**:
  - Withdrawal amount
  - Bank name
  - Account number
  - Account name
  - User verification

#### Admin Processing Workflow
- **Filament Admin Interface**: Withdrawal management table
- **Status Flow**: `pending` → `processing` → `paid` / `rejected`
- **Actions Available**:
  - Approve withdrawal (status: `paid`)
  - Reject withdrawal (status: `rejected` with reason)
  - Mark as processing (status: `processing`)

#### Automated Actions on Status Change

##### For Successful Withdrawals (`paid`)
1. **Wallet Debit**: Automatic deduction from user wallet
2. **Email Notification**: Professional success email with:
   - Transaction timeline
   - Bank processing information (1-3 business days)
   - Transaction reference
   - Payment receipt details
3. **In-App Notification**: Success notification with transaction details

##### For Rejected Withdrawals (`rejected`)
1. **Fund Security**: Amount remains in user wallet
2. **Email Notification**: Professional rejection email with:
   - Clear rejection reason
   - Next steps guidance
   - Funds safety assurance
   - Support contact information
3. **In-App Notification**: Rejection notification with reason

### 3. Email Templates

#### Unified Template System
- **Main Template**: `withdrawal_status.blade.php`
- **Dynamic Styling**: Status-based colors and content
- **Responsive Design**: Mobile and desktop optimized

#### Template Features
- **Success Template**: Celebration graphics, timeline, bank info
- **Rejection Template**: Clear messaging, reason display, next steps
- **Shared Components**: Professional branding, support info, company footer

### 4. Security Features
- **Two-Factor Authentication**: Required for large withdrawals
- **Daily Limits**: Configurable withdrawal limits
- **Fraud Detection**: Automated suspicious activity monitoring
- **Admin Verification**: Manual review for high-value transactions

## Communication & Support

### 1. Chat System
- **Endpoints**:
  - `GET /api/chat/conversations`
  - `POST /api/chat/conversations/start`
  - `POST /api/chat/send-message`

- **Features**:
  - Real-time messaging
  - File attachments
  - Conversation history
  - Multi-party chats (tenant, landlord, agent)

### 2. Notification System
- **Endpoints**:
  - `GET /api/notifications`
  - `POST /api/notifications`
  - `PATCH /api/notifications/{id}/read`

- **Types**:
  - Payment reminders
  - Property updates
  - Message notifications
  - System announcements

### 3. Support Features
- Issue reporting
- Ticket management
- FAQ and help center
- Direct admin support

## Email Notification System

### 1. Comprehensive Email Templates

#### Rent Payment Notifications
- **Template**: `rent_payment_status.blade.php`
- **Triggers**: Payment status changes, payment reminders
- **Features**:
  - Status-specific messaging and styling
  - Property and payment details
  - Responsive design for all devices
  - Professional branding and layout

#### Withdrawal Status Notifications
- **Template**: `withdrawal_status.blade.php` (unified template)
- **Sub-templates**:
  - `withdrawal_successful.blade.php` - Success notifications
  - `withdrawal_rejected.blade.php` - Rejection notifications
- **Features**:
  - Dynamic content based on withdrawal status
  - Timeline visualization for successful withdrawals
  - Clear reason display for rejections
  - Call-to-action buttons and support information

### 2. Email Template Architecture

#### Design Features
- **Professional Layout**: Modern, clean design with gradient headers
- **Responsive Design**: Optimized for mobile and desktop viewing
- **Status-based Styling**: Dynamic colors (green for success, red for rejection)
- **Brand Consistency**: Unified ShelterBaze9ja branding across all templates

#### Content Components
- **Personalized Greetings**: User name integration
- **Transaction Details**: Comprehensive information tables
- **Status Indicators**: Visual badges and icons
- **Next Steps**: Clear guidance for user actions
- **Support Information**: Multiple contact channels
- **Legal Footer**: Company info and disclaimers

### 3. Automated Email Triggers

#### System-Generated Emails
- **Registration**: Welcome and verification emails
- **Payment Processing**: Status updates and receipts
- **Withdrawal Processing**: Status notifications
- **Rental Management**: Agreement updates and renewals
- **Property Updates**: Listing status changes

#### Notification Categories
- **Transactional**: Payment confirmations, withdrawal updates
- **Operational**: System maintenance, policy updates
- **Marketing**: Feature announcements, tips and insights
- **Support**: Help requests, ticket updates

### 4. Email Delivery Infrastructure
- **Queue Management**: Background job processing for email delivery
- **Template Caching**: Optimized rendering for high-volume sending
- **Delivery Tracking**: Email open and click tracking
- **Failure Handling**: Retry mechanisms and error logging

## Biometric Authentication

### 1. Security Enhancement
- **Purpose**: Enhanced security layer for sensitive operations
- **Implementation**: Fingerprint and facial recognition support
- **Integration**: Mobile app biometric APIs

### 2. Authentication Endpoints
- **Endpoints**:
  - `POST /api/auth/biometric/authenticate` - Authenticate using biometrics
  - `POST /api/auth/biometric/enroll` - Enroll biometric data
  - `GET /api/auth/biometric/status` - Check biometric enrollment status
  - `DELETE /api/auth/biometric/disable` - Disable biometric authentication

### 3. Biometric Features
- **Enrollment Process**: Secure biometric data capture and storage
- **Authentication Flow**: Seamless login without passwords
- **Fallback Options**: Traditional login methods remain available
- **Security Standards**: Industry-standard encryption and storage

### 4. Use Cases
- **User Login**: Quick access to user accounts
- **Payment Authorization**: Secure transaction approval
- **Sensitive Operations**: Account modifications, withdrawals
- **Admin Functions**: Administrative action verification

## Automated Jobs & Scheduling

### 1. Agreement Management Automation

#### Expired Agreement Monitoring
- **Job**: `CheckExpiredAgreementJob`
- **Schedule**: Daily execution at midnight
- **Function**: Automatic status update for expired rental agreements
- **Implementation**: Laravel task scheduler via `routes/console.php`

#### Job Features
- **Bulk Processing**: Efficient handling of multiple agreements
- **Safe Filtering**: Only processes truly expired agreements
- **Status Updates**: Automatic change from `active` to `expired`
- **Audit Trail**: Comprehensive logging of all changes

### 2. Scheduler Configuration
- **Location**: `routes/console.php` (Laravel 11 compatible)
- **Execution**: `Schedule::job(CheckExpiredAgreementJob::class)->daily()`
- **Monitoring**: Built-in Laravel scheduler monitoring
- **Error Handling**: Failed job retry mechanisms

### 3. Additional Scheduled Tasks
- **Payment Reminders**: Daily payment due notifications
- **Property Verification**: Automated reminder for pending verifications
- **Analytics Processing**: Daily/weekly analytics calculations
- **Data Cleanup**: Periodic cleanup of temporary data

### 4. Job Management Features
- **Queue Processing**: Background job execution
- **Error Logging**: Comprehensive error tracking
- **Performance Monitoring**: Job execution time tracking
- **Manual Triggers**: Admin ability to manually trigger jobs

## Additional Features

### 1. Rent Savings
- **Purpose**: Help tenants save for rent payments
- **Endpoints**:
  - `POST /api/savings-mgt/deposit`
  - `GET /api/savings-mgt/dashboard`
  - `POST /api/savings-mgt/withdraw`

- **Features**:
  - Automatic savings plans
  - Goal-based saving
  - Interest earning
  - Withdrawal management

### 2. Favorites System
- **Endpoint**: `POST /api/listing/toggle-favorite/{id}`
- Save properties for later viewing
- Quick access to preferred properties

### 3. Enhanced Wallet System
- **Digital Wallet Features**:
  - Real-time balance tracking
  - Multi-transaction support
  - Withdrawal management with admin approval
  - Transaction history and analytics
  - Security features and fraud detection

- **Withdrawal Management**:
  - User-initiated withdrawal requests
  - Admin approval workflow via Filament interface
  - Automated email notifications for status changes
  - Secure fund transfer processing
  - Comprehensive audit trail

### 4. Analytics & Insights
- **Endpoints**:
  - `GET /api/rent/insights`
  - `GET /api/savings-mgt/insights`
  - `GET /api/rent/rental-details/{id}`
  - `GET /api/rent/payment-summary`
  - `GET /api/rent/export-rental-data`

- **Features**:
  - Payment analytics and trends
  - Property performance metrics
  - Market insights and recommendations
  - User behavior analytics
  - Rental insights with personalized recommendations
  - Data export capabilities for record keeping

### 5. Advanced Rental Management
- **Agreement Management**:
  - `GET /api/rent/rental-agreement/{id}` - Detailed agreement information
  - `POST /api/rent/renewal` - Request lease renewal
  - `POST /api/rent/cancel-rental/{id}` - Cancel rental agreements
  - `POST /api/rent/early-termination` - Request early termination

- **Issue Management**:
  - `POST /api/rent/report-issue` - Report property issues
  - Priority-based issue categorization
  - Automated notifications to landlords and agents
  - Support ticket integration

- **Apartment Management**:
  - `GET /api/rent/my-apartments` - View all rented properties
  - Lease progress tracking
  - Payment status monitoring
  - Expiry notifications and renewal reminders

## Business Logic Updates

### Recent Platform Changes

#### 1. Engagement Fee Requirement Removed
- **Change**: Engagement fee payment is no longer required to access landlord contact details
- **Impact**: Tenants can directly contact landlords without upfront payment
- **Implementation**: Code commented out but preserved for potential future re-enablement
- **Benefit**: Reduces barriers to tenant-landlord communication

#### 2. Enhanced Rental Agreement Process
- **Multiple Agreements**: Users can now maintain up to 5 active rental agreements simultaneously
- **Flexible Creation**: Rental agreements can be created during:
  - Invoice generation process
  - Payment proof upload (for offline payments)
- **Duplicate Prevention**: Uses `updateOrCreate` to prevent duplicate agreements for same property-tenant combination

#### 3. Payment Processing Improvements
- **Offline Payment Flexibility**: Can create new rental agreements during payment upload
- **Payment Tolerance**: 2% tolerance range for payment amounts to account for bank charges
- **Enhanced Validation**: Comprehensive amount validation against expected totals
- **Better Error Handling**: Clear error messages for various payment scenarios

#### 4. Discount and Pricing Features
- **Long-term Rental Discounts**: 
  - 5% discount for rental periods of 12+ months
  - Applied to rent amount, not commission
- **Renewal Incentives**:
  - 5% discount for returning tenants
  - Early renewal requests allowed within 90 days of expiry

#### 5. Enhanced User Experience
- **Streamlined Onboarding**: Reduced friction in tenant journey
- **Improved Payment Flow**: Better support for both online and offline payments
- **Comprehensive Analytics**: Enhanced insights and reporting capabilities

#### 6. Advanced Security Features
- **Biometric Authentication**: Optional biometric login for enhanced security
- **Withdrawal Security**: Multi-layer approval process for fund withdrawals
- **Audit Trail**: Comprehensive logging of all sensitive operations
- **Two-Factor Authentication**: Enhanced security for critical operations

#### 7. Automated System Management
- **Job Scheduling**: Automated management of expired agreements
- **Email Automation**: Professional email templates for all system notifications
- **Background Processing**: Queue-based job processing for heavy operations
- **Performance Optimization**: Enhanced database queries and caching

#### 8. Property Management Enhancements
- **Facilities Management**: Improved handling of property facilities with form data support
- **Media Upload**: Enhanced property image and video management
- **Verification Workflow**: Streamlined agent property verification process
- **Status Management**: Improved property status tracking and updates

#### 9. Financial Management Improvements
- **Wallet Integration**: Comprehensive digital wallet system
- **Withdrawal Processing**: Automated withdrawal workflow with admin controls
- **Payment Validation**: Enhanced payment amount validation with tolerance ranges
- **Commission Calculation**: Automated commission and discount calculations

### Configuration Settings
- **Engagement Fee**: Configurable via `SystemSetting::get('engagement_fee', 5000)`
- **Bank Details**: Stored in system settings with fallback defaults
- **Commission Rate**: 10% of rent amount (configurable)
- **Maximum Active Rentals**: 5 per user (hardcoded, can be made configurable)
- **Withdrawal Limits**: Configurable daily/monthly withdrawal limits
- **Email Templates**: Customizable email templates via Blade views
- **Job Scheduling**: Laravel scheduler configuration in `routes/console.php`
- **Biometric Settings**: Configurable biometric authentication requirements
- **Payment Tolerance**: 2% tolerance for payment amount variations
- **File Upload Limits**: Configurable limits for property media uploads

## Security & Verification

### 1. User Verification
- Email verification required
- Phone OTP verification
- NIN verification with selfie
- Document verification

### 2. Property Verification
- Agent-conducted inspections
- Document verification
- Physical property confirmation
- Fraud prevention measures

### 3. Payment Security
- Secure payment gateways
- Payment verification processes
- Fraud detection
- Refund mechanisms

## API Authentication

All protected endpoints require:
- **Authentication**: `Bearer Token` (Sanctum)
- **Verification**: Verified email and phone
- **Headers**: 
  ```
  Authorization: Bearer {token}
  Content-Type: application/json
  Accept: application/json
  ```

## Status Flows

### User Account Status
- `pending` → `active` → `suspended` (if needed)

### Property Status
- `pending` → `verified` → `open` → `rented` → `closed`

### Payment Status
- `pending` → `verified` / `rejected`

### Agreement Status
- `pending` → `active` → `expired` / `terminated`

### Withdrawal Status
- `pending` → `processing` → `paid` / `rejected`

### Biometric Status
- `not_enrolled` → `enrolled` → `active` / `disabled`

## Technical Architecture

### Database Design
- **User Management**: Multi-role user system with role-based permissions
- **Property Management**: Comprehensive property listing with media support
- **Financial System**: Integrated wallet, payment, and withdrawal management
- **Communication**: Real-time chat and notification system
- **Audit System**: Comprehensive logging and audit trail

### API Design Principles
- **RESTful Architecture**: Standard HTTP methods and status codes
- **Authentication**: Sanctum token-based authentication
- **Authorization**: Role-based access control (RBAC)
- **Validation**: Comprehensive request validation with form requests
- **Error Handling**: Standardized error responses and logging

### Performance Optimization
- **Database Optimization**: Proper indexing and query optimization
- **Caching**: Redis-based caching for frequently accessed data
- **Queue System**: Background job processing for heavy operations
- **File Storage**: Cloudinary integration for media management
- **API Rate Limiting**: Configurable rate limiting for API endpoints

This documentation provides a comprehensive overview of the ShelterBaze platform's enhanced features, including the new withdrawal system, biometric authentication, automated job scheduling, and professional email notification system. Each section includes relevant technical details and implementation guidelines to ensure smooth development and maintenance.