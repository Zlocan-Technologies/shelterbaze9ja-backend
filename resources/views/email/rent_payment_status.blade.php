<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Payment Status Update</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin: 10px 0;
        }
        .status-verified {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .content-section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #2563eb;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            color: #1f2937;
            font-size: 14px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
        }
        .message-box {
            background-color: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 10px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .email-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">ShelterBaze</div>
            <h1 style="margin: 0; color: #1f2937;">Rent Payment Status Update</h1>
            <div class="status-badge status-{{ strtolower($record->status ?? 'pending') }}">
                {{ ucfirst($record->status ?? 'Pending') }}
            </div>
        </div>

        <!-- Greeting -->
        <div class="content-section">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Dear {{ $user->first_name ?? 'Landlord' }},
            </p>
            <p>
                We are writing to inform you about a rent payment status update for one of your properties.
            </p>
        </div>

        <!-- Payment Details -->
        <div class="content-section">
            <h2 class="section-title">Payment Details</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Tenant Name</div>
                    <div class="info-value">{{ $record->user->first_name ?? 'N/A' }} {{ $record->user->last_name ?? '' }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Payment Amount</div>
                    <div class="info-value amount">₦{{ number_format($record->amount ?? 0, 2) }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Payment Type</div>
                    <div class="info-value">{{ ucfirst($record->payment_type ?? 'N/A') }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Payment Date</div>
                    <div class="info-value">{{ $record->payment_date ? \Carbon\Carbon::parse($record->payment_date)->format('M d, Y') : 'N/A' }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Due Date</div>
                    <div class="info-value">{{ $record->due_date ? \Carbon\Carbon::parse($record->due_date)->format('M d, Y') : 'N/A' }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-{{ strtolower($record->status ?? 'pending') }}">
                            {{ ucfirst($record->status ?? 'Pending') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Information -->
        @if($record->rentalAgreement && $record->rentalAgreement->property)
        <div class="content-section">
            <h2 class="section-title">Property Information</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Property Title</div>
                    <div class="info-value">{{ $record->rentalAgreement->property->title ?? 'N/A' }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value">{{ $record->rentalAgreement->property->location_address ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Bank Details (if applicable) -->
        @if($record->bank_name || $record->bank_account_number)
        <div class="content-section">
            <h2 class="section-title">Bank Details</h2>
            
            <div class="info-grid">
                @if($record->bank_name)
                <div class="info-item">
                    <div class="info-label">Bank Name</div>
                    <div class="info-value">{{ $record->bank_name }}</div>
                </div>
                @endif
                
                @if($record->account_name)
                <div class="info-item">
                    <div class="info-label">Account Name</div>
                    <div class="info-value">{{ $record->account_name }}</div>
                </div>
                @endif
                
                @if($record->bank_account_number)
                <div class="info-item">
                    <div class="info-label">Account Number</div>
                    <div class="info-value">{{ $record->bank_account_number }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Status-specific Messages -->
        <div class="message-box">
            @if($record->status === 'verified')
                <h3 style="color: #059669; margin-top: 0;">✅ Payment Verified</h3>
                <p>Great news! The rent payment has been successfully verified and processed. 
                @if($record->payment_type === 'online')
                The funds have been credited to your wallet.
                @else
                Please proceed with any necessary follow-up actions.
                @endif
                </p>
            @elseif($record->status === 'rejected')
                <h3 style="color: #dc2626; margin-top: 0;">❌ Payment Rejected</h3>
                <p>The rent payment has been rejected. Please contact the tenant to resolve any issues and resubmit the payment with correct details.</p>
            @else
                <h3 style="color: #d97706; margin-top: 0;">⏳ Payment Pending</h3>
                <p>The rent payment is currently under review. We will notify you once the verification process is complete.</p>
            @endif
        </div>

        <!-- Next Steps -->
        <div class="content-section">
            <h2 class="section-title">Next Steps</h2>
            @if($record->status === 'verified')
                <ul style="padding-left: 20px;">
                    <li>The rental agreement is now active</li>
                    <li>You can access your updated wallet balance in your dashboard</li>
                    <li>Next payment due: {{ $record->next_due_date ? \Carbon\Carbon::parse($record->next_due_date)->format('M d, Y') : 'TBD' }}</li>
                </ul>
            @elseif($record->status === 'rejected')
                <ul style="padding-left: 20px;">
                    <li>Contact your tenant to discuss the rejection reason</li>
                    <li>Request a new payment with correct details</li>
                    <li>Monitor for resubmission in your dashboard</li>
                </ul>
            @else
                <ul style="padding-left: 20px;">
                    <li>Monitor the payment status in your dashboard</li>
                    <li>You will receive another notification once reviewed</li>
                </ul>
            @endif
        </div>

        <!-- Call to Action -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="#" class="button">View Dashboard</a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>ShelterBaze</strong> - Your Trusted Property Management Platform</p>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>If you have any questions, please contact our support team.</p>
            <p style="margin-top: 20px;">
                © {{ date('Y') }} ShelterBaze. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
