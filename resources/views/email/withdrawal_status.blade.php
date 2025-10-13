<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Request {{ ucfirst($status ?? 'Update') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, 
                {{ ($status ?? '') === 'paid' ? '#28a745, #20c997' : '#667eea, #764ba2' }});
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header .subtitle {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 25px;
            color: #2c3e50;
        }
        .message {
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .alert-box {
            background-color: {{ ($status ?? '') === 'paid' ? '#d4edda' : '#fee' }};
            border: 1px solid {{ ($status ?? '') === 'paid' ? '#c3e6cb' : '#fcc' }};
            border-left: 4px solid {{ ($status ?? '') === 'paid' ? '#28a745' : '#e74c3c' }};
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .alert-box .alert-title {
            font-weight: 600;
            color: {{ ($status ?? '') === 'paid' ? '#155724' : '#c0392b' }};
            margin-bottom: 10px;
            font-size: 16px;
        }
        .alert-box .alert-content {
            color: {{ ($status ?? '') === 'paid' ? '#155724' : '#721c24' }};
            line-height: 1.6;
        }
        .withdrawal-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            border: 1px solid #e9ecef;
        }
        .withdrawal-details h3 {
            margin: 0 0 20px 0;
            color: #495057;
            font-size: 18px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
        }
        .detail-value {
            color: #495057;
            text-align: right;
            flex: 1;
        }
        .amount {
            font-size: 24px;
            font-weight: 700;
            color: {{ ($status ?? '') === 'paid' ? '#28a745' : '#e74c3c' }};
        }
        .status-badge {
            background-color: {{ ($status ?? '') === 'paid' ? '#28a745' : '#e74c3c' }};
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .next-steps {
            background-color: {{ ($status ?? '') === 'paid' ? '#d1ecf1' : '#e8f4fd' }};
            border-left: 4px solid {{ ($status ?? '') === 'paid' ? '#17a2b8' : '#007bff' }};
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .next-steps h3 {
            margin: 0 0 15px 0;
            color: {{ ($status ?? '') === 'paid' ? '#0c5460' : '#0056b3' }};
            font-size: 16px;
        }
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
            color: #495057;
        }
        .next-steps li {
            margin-bottom: 8px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, 
                {{ ($status ?? '') === 'paid' ? '#28a745 0%, #20c997 100%' : '#007bff 0%, #0056b3 100%' }});
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba({{ ($status ?? '') === 'paid' ? '40, 167, 69' : '0, 123, 255' }}, 0.3);
        }
        .success-celebration {
            text-align: center;
            padding: 20px;
            margin: 25px 0;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-radius: 8px;
            border: 1px solid #c3e6cb;
        }
        .success-celebration .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .success-celebration h3 {
            color: #155724;
            margin: 10px 0;
            font-size: 20px;
        }
        .success-celebration p {
            color: #155724;
            margin: 0;
        }
        .timeline {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .timeline h3 {
            margin: 0 0 20px 0;
            color: #495057;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: white;
            border-radius: 6px;
            border-left: 4px solid #28a745;
        }
        .timeline-item .step {
            background-color: #28a745;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
            font-size: 14px;
        }
        .timeline-item .content {
            flex: 1;
        }
        .timeline-item .time {
            color: #6c757d;
            font-size: 12px;
            margin-top: 5px;
        }
        .support-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: center;
        }
        .support-info h3 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        .support-info p {
            margin: 5px 0;
            color: #6c757d;
        }
        .footer {
            background-color: #343a40;
            color: #adb5bd;
            padding: 30px;
            text-align: center;
            font-size: 14px;
        }
        .footer .company-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .footer .links {
            margin: 15px 0;
        }
        .footer .links a {
            color: #007bff;
            text-decoration: none;
            margin: 0 10px;
        }
        .footer .disclaimer {
            margin-top: 20px;
            font-size: 12px;
            color: #868e96;
            line-height: 1.5;
        }
        @media (max-width: 600px) {
            .container {
                margin: 0;
                box-shadow: none;
            }
            .header, .content {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .detail-value {
                text-align: left;
                margin-top: 5px;
            }
            .timeline-item {
                flex-direction: column;
                text-align: center;
            }
            .timeline-item .step {
                margin-bottom: 10px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
    
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $user->first_name ?? '' }},
            </div>

            @if(($status ?? '') === 'paid')
                <!-- Success Celebration -->
                <div class="success-celebration">
                    <div class="icon">🎉</div>
                    <h3>Withdrawal Successful!</h3>
                    <p>Your funds have been transferred to your bank account</p>
                </div>

                <div class="message">
                    Great news! Your withdrawal request has been successfully processed. The funds should appear in your bank account within the next 1-3 business days, depending on your bank's processing time.
                </div>

                <!-- Alert Box -->
                {{-- <div class="alert-box">
                    <div class="alert-title">✅ Payment Completed</div>
                    <div class="alert-content">
                        Your withdrawal request has been approved and the payment has been sent to your registered bank account.
                    </div>
                </div> --}}
            @else
                <div class="message">
                    We hope this email finds you well. We are writing to inform you about the status of your recent withdrawal request.
                </div>

                <!-- Alert Box -->
                <div class="alert-box">
                    <div class="alert-title">❌ Withdrawal Request Rejected</div>
                    <div class="alert-content">
                        Unfortunately, your withdrawal request has been rejected after careful review by our team.
                    </div>
                </div>
            @endif

            <!-- Withdrawal Details -->
            <div class="withdrawal-details">
                <h3>📋 Withdrawal Request Details</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Request Date:</span>
                    <span class="detail-value">{{ $extra['record']->created_at->format('F j, Y, g:i a') ?? '' }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value amount">₦{{ number_format($extra['record']->amount ?? 0, 2) }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge">
                            {{  $status === 'paid' ? 'Paid' : 'Rejected' }}
                        </span>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Bank:</span>
                    <span class="detail-value">{{ $extra['record']->bank_name ?? '' }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Account:</span>
                    <span class="detail-value">{{ $extra['record']->account_number ?? '' }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Account Name:</span>
                    <span class="detail-value">{{ $extra['record']->account_name ?? '' }}</span>
                </div>
            </div>

            @if(($status ?? '') === 'paid')
            

                <!-- Next Steps for Success -->
                {{-- <div class="next-steps">
                    <h3>💰 What happens next?</h3>
                    <ul>
                        <li><strong>Bank Processing:</strong> Your bank will process the transfer within 1-3 business days</li>
                        <li><strong>Account Credit:</strong> Funds will appear in your {{ $bank_name ?? 'bank' }} account ending in {{ isset($account_number) ? '***' . substr($account_number, -4) : '****' }}</li>
                        <li><strong>Transaction Record:</strong> Keep this email as proof of transaction for your records</li>
                        <li><strong>Account Statement:</strong> The transfer will appear on your bank statement with our reference</li>
                    </ul>
                </div> --}}

                <div class="message">
                    <strong>Important Note:</strong> If you don't see the funds in your account after 3 business days, please contact your bank first to check for any delays, then reach out to our support team if needed.
                </div>
            @else
                <!-- Rejection Reason -->
                {{-- @if(isset($reason) && !empty($reason)) --}}
                <div class="alert-box">
                    <div class="alert-title">📝 Reason for Rejection</div>
                    <div class="alert-content">
                        {{ $extra['reason'] ?? '' }}
                    </div>
                </div>
                {{-- @endif --}}

                <!-- Next Steps for Rejection -->
                {{-- <div class="next-steps">
                    <h3>🔄 What happens next?</h3>
                    <ul>
                        <li><strong>Your funds remain safe:</strong> The requested amount has been returned to your wallet balance</li>
                        <li><strong>Review the reason:</strong> Please review the rejection reason above to understand what needs to be corrected</li>
                        <li><strong>Submit a new request:</strong> You can submit a new withdrawal request after addressing the issues mentioned</li>
                        <li><strong>Contact support:</strong> If you need clarification or assistance, our support team is here to help</li>
                    </ul>
                </div> --}}

                {{-- <div class="message">
                    We understand this may be disappointing, but this process helps us ensure the security and compliance of all transactions on our platform.
                </div> --}}
            @endif

    
            <div class="message">
                @if(($status ?? '') === 'paid')
                    Thank you for trusting ShelterBaze9ja with your financial transactions. We're committed to providing you with secure and reliable services.
                @else
                    Thank you for your understanding and for choosing ShelterBaze9ja for your property and financial needs.
                @endif
            </div>

            <div style="margin-top: 30px;">
                <strong>Best regards,</strong><br>
                The ShelterBaze9ja Team
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="company-name">ShelterBaze9ja</div>
          
            <div class="disclaimer">
                This email was sent to {{ $user->email }}. If you have any concerns about this email, 
                please contact our support team immediately. This is an automated message, please do not reply directly to this email.
                <br><br>
                © {{ date('Y') }} ShelterBaze9ja. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>