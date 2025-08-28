<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number', 
        'password', 'role', 'profile_completed', 'account_status',
        'email_verified_at', 'fcm_token'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'profile_completed' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    // Constants for roles
    const ROLE_USER = 'user';
    const ROLE_LANDLORD = 'landlord';
    const ROLE_AGENT = 'agent';
    const ROLE_ADMIN = 'admin';

    // Constants for account status
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_DECLINED = 'declined';
    const STATUS_SUSPENDED = 'suspended';

    // Relationships
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function agentAssignments()
    {
        return $this->hasMany(AgentAssignment::class, 'agent_id');
    }

    public function landlordAssignments()
    {
        return $this->hasMany(AgentAssignment::class, 'landlord_id');
    }

    public function rentSavings()
    {
        return $this->hasMany(RentSaving::class);
    }

    public function savingsTransactions()
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function engagementFees()
    {
        return $this->hasMany(EngagementFee::class);
    }

    public function tenantAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'tenant_id');
    }

    public function landlordAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'landlord_id');
    }

    public function rentPayments()
    {
        return $this->hasMany(RentPayment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function propertyVerifications()
    {
        return $this->hasMany(PropertyVerification::class, 'agent_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function chatConversationsAsUser()
    {
        return $this->hasMany(ChatConversation::class, 'user_id');
    }

    public function chatConversationsAsLandlord()
    {
        return $this->hasMany(ChatConversation::class, 'landlord_id');
    }

    public function chatConversationsAsAgent()
    {
        return $this->hasMany(ChatConversation::class, 'agent_id');
    }

    // Scopes
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('account_status', self::STATUS_ACTIVE);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at')
                    ->whereNotNull('phone_verified_at');
    }

    public function scopeProfileCompleted($query)
    {
        return $query->where('profile_completed', true);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getInitialsAttribute()
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    // Mutators
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isLandlord()
    {
        return $this->role === self::ROLE_LANDLORD;
    }

    public function isAgent()
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function isUser()
    {
        return $this->role === self::ROLE_USER;
    }

    public function isActive()
    {
        return $this->account_status === self::STATUS_ACTIVE;
    }

    public function isVerified()
    {
        return $this->email_verified_at && $this->phone_verified_at;
    }

    public function canAccessProperty($property)
    {
        // Check if user has paid engagement fee for this property
        return $this->engagementFees()
                    ->where('property_id', $property->id)
                    ->where('payment_status', 'completed')
                    ->exists();
    }

    public function getTotalSavingsAttribute()
    {
        return $this->rentSavings()->sum('current_amount');
    }

    public function getUnreadNotificationsCountAttribute()
    {
        return $this->notifications()->where('is_read', false)->count();
    }
}
