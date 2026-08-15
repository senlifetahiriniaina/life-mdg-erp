<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Contact;

class CustomerManagementService
{
    const CACHE_TTL = 86400;

    /**
     * Create customer with initial setup
     */
    public function createCustomer(array $data): array
    {
        $customer = Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'status' => 'active',
            'tier' => $this->calculateTier($data),
            'lifetime_value' => 0,
            'created_by' => auth()->id(),
        ]);

        // Create primary contact
        if (!empty($data['primary_contact'])) {
            // Contact has no customer_id/is_primary/name columns — it links to Account/
            // Owner/Company/Lead instead, and splits the person's name into
            // first_name/last_name. Match Contact's real schema rather than the
            // Customer-linkage fields this block used to assume existed.
            [$firstName, $lastName] = array_pad(
                explode(' ', $data['primary_contact']['name'], 2),
                2,
                ''
            );

            Contact::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $data['primary_contact']['email'],
                'phone' => $data['primary_contact']['phone'] ?? null,
            ]);
        }

        $this->clearCache();

        return [
            'customer_id' => $customer->id,
            'status' => 'created',
            'tier' => $customer->tier,
            'message' => 'Customer created successfully',
        ];
    }

    /**
     * Update customer information
     */
    public function updateCustomer(int $customerId, array $data): array
    {
        $customer = Customer::findOrFail($customerId);

        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'email' => $data['email'] ?? $customer->email,
            'phone' => $data['phone'] ?? $customer->phone,
            'company' => $data['company'] ?? $customer->company,
            'status' => $data['status'] ?? $customer->status,
        ]);

        // Recalculate tier if needed
        if (isset($data['revenue']) || isset($data['interaction_count'])) {
            $customer->tier = $this->calculateTier($customer->toArray());
            $customer->save();
        }

        $this->clearCache();

        return [
            'customer_id' => $customer->id,
            'status' => 'updated',
            'message' => 'Customer updated successfully',
        ];
    }

    /**
     * Get customer with full details
     */
    public function getCustomerDetails(int $customerId): ?array
    {
        $cacheKey = "customer:{$customerId}:details";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($customerId) {
            $customer = Customer::with('contacts', 'interactions', 'opportunities')->findOrFail($customerId);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company' => $customer->company,
                'status' => $customer->status,
                'tier' => $customer->tier,
                'lifetime_value' => $customer->lifetime_value,
                'contacts' => $customer->contacts->count(),
                'interactions' => $customer->interactions->count(),
                'opportunities' => $customer->opportunities->count(),
                'created_at' => $customer->created_at,
                'last_interaction' => $customer->interactions()->latest()->first()?->created_at,
            ];
        });
    }

    /**
     * Search customers with filters
     */
    public function searchCustomers(array $filters = []): array
    {
        $query = Customer::query();

        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (!empty($filters['company'])) {
            $query->where('company', 'like', "%{$filters['company']}%");
        }

        $customers = $query->paginate($filters['per_page'] ?? 15);

        return [
            'total' => $customers->total(),
            'per_page' => $customers->perPage(),
            'current_page' => $customers->currentPage(),
            'customers' => $customers->items(),
        ];
    }

    /**
     * Get customers by tier
     */
    public function getCustomersByTier(string $tier): array
    {
        $cacheKey = "customers:tier:{$tier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tier) {
            return Customer::where('tier', $tier)
                ->where('status', 'active')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'lifetime_value' => $c->lifetime_value,
                ])
                ->toArray();
        });
    }

    /**
     * Calculate customer tier based on metrics
     */
    private function calculateTier(array $data): string
    {
        $lifetimeValue = $data['lifetime_value'] ?? 0;
        $interactions = $data['interaction_count'] ?? 0;

        if ($lifetimeValue >= 100000 || $interactions >= 50) {
            return 'platinum';
        } elseif ($lifetimeValue >= 50000 || $interactions >= 25) {
            return 'gold';
        } elseif ($lifetimeValue >= 10000 || $interactions >= 10) {
            return 'silver';
        }

        return 'bronze';
    }

    /**
     * Clear cache
     */
    private function clearCache(): void
    {
        Cache::tags(['customers'])->flush();
    }
}
