<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Webhook;
use App\Services\RolePermissionService;
use App\Policies\BomPolicy;
use App\Policies\BroadcastCampaignPolicy;
use App\Policies\CapacityAllocationPolicy;
use App\Policies\CapacityConstraintPolicy;
use App\Policies\EcommerceProductPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\RolePolicy;
use App\Policies\ConfiguratorRulePolicy;
use App\Policies\ContactPolicy;
use App\Policies\AccountingPolicy;
use Modules\Ecommerce\Policies\CartPolicy;
use App\Policies\CouponPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\WebhookPolicy;
use App\Policies\CrmAccountPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EmailSegmentPolicy;
use App\Policies\EmailTemplatePolicy;
use Modules\HR\Policies\EmployeePolicy;
use App\Policies\FolderPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\JournalPolicy;
use App\Policies\LeadPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OutsourcedOrderPolicy;
use App\Policies\PayrollRecordPolicy;
use App\Policies\ProductionOrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\RfqPolicy;
use App\Policies\RoutingPolicy;
use App\Policies\ScoringRulePolicy;
use App\Policies\StorePolicy;
use App\Policies\SubcontractorPolicy;
use App\Policies\SubscriberPolicy;
use App\Policies\TaskPolicy;
use Modules\Helpdesk\Policies\TicketPolicy;
use App\Policies\WaTemplatePolicy;
use App\Policies\WorkcenterPolicy;
use App\Policies\WorkOrderPolicy;
use App\Policies\PosOrderPolicy;
use App\Policies\PosReturnPolicy;
use App\Policies\PosSessionPolicy;
use App\Policies\QualityIssuePolicy;
use App\Policies\ShiftPolicy;
use Modules\Achats\Policies\PurchaseOrderPolicy;
use App\Policies\SupplierQuotePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\SupplierQuote;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\BI\Models\Dashboard;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\ScoringRule;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\Folder;
use Modules\Ecommerce\Models\Cart;
use Modules\Ecommerce\Models\ConfiguratorRule;
use Modules\Ecommerce\Models\Coupon;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\Promotion;
use Modules\Ecommerce\Models\Review;
use Modules\Ecommerce\Models\Rfq;
use Modules\Ecommerce\Models\Store;
use Modules\Email\Models\Campaign;
use Modules\Helpdesk\Models\Ticket;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\PayrollRecord;
use Modules\Inventory\Models\Product;
use Modules\Manufacturing\Models\Bom;
use Modules\Manufacturing\Models\CapacityAllocation;
use Modules\Manufacturing\Models\CapacityConstraint;
use Modules\Manufacturing\Models\OutsourcedOrder;
use Modules\Manufacturing\Models\ProductionOrder;
use Modules\Manufacturing\Models\Routing;
use Modules\Manufacturing\Models\Subcontractor;
use Modules\Manufacturing\Models\WorkOrder;
use Modules\Manufacturing\Models\Workcenter;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\WhatsApp\Models\BroadcastCampaign;
use Modules\WhatsApp\Models\Conversation;
use Modules\WhatsApp\Models\WaTemplate;
use Modules\Email\Models\EmailSegment;
use Modules\Email\Models\EmailTemplate;
use Modules\Email\Models\Subscriber;
use Modules\POS\Models\PosOrder;
use Modules\POS\Models\PosReturn;
use Modules\POS\Models\PosSession;
use Modules\POS\Models\Shift;
use Modules\Quality\Models\QualityIssue;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Policies\ApprovalRequestPolicy;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Policies\TimesheetEntryPolicy;
use Modules\PLM\Models\Product as PLMProduct;
use Modules\PLM\Models\BillOfMaterial;
use Modules\PLM\Models\ProductChange;
use Modules\PLM\Policies\ProductPolicy as PLMProductPolicy;
use Modules\PLM\Policies\BOMPolicy as PLMBOMPolicy;
use Modules\PLM\Policies\ChangePolicy;
use Modules\WorkflowAutomation\Models\Workflow;
use App\Policies\WorkflowPolicy;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /** Model → Policy map. Laravel auto-discovery also covers this, but explicit is cleaner. */
    protected array $policies = [
        Account::class          => CrmAccountPolicy::class,
        Contact::class          => ContactPolicy::class,
        Lead::class             => LeadPolicy::class,
        Opportunity::class      => OpportunityPolicy::class,
        ScoringRule::class      => ScoringRulePolicy::class,
        Invoice::class          => InvoicePolicy::class,
        Journal::class          => JournalPolicy::class,
        JournalEntry::class     => AccountingPolicy::class,
        Ticket::class           => TicketPolicy::class,
        Employee::class         => EmployeePolicy::class,
        PayrollRecord::class    => PayrollRecordPolicy::class,
        LeaveRequest::class     => LeaveRequestPolicy::class,
        Product::class          => ProductPolicy::class,
        Document::class         => DocumentPolicy::class,
        Folder::class           => FolderPolicy::class,
        Project::class          => ProjectPolicy::class,
        Task::class             => TaskPolicy::class,
        Campaign::class         => CampaignPolicy::class,
        Order::class            => OrderPolicy::class,
        ProductionOrder::class  => ProductionOrderPolicy::class,
        Dashboard::class        => DashboardPolicy::class,
        ConfiguratorRule::class => ConfiguratorRulePolicy::class,
        Subcontractor::class    => SubcontractorPolicy::class,
        OutsourcedOrder::class  => OutsourcedOrderPolicy::class,
        WorkOrder::class        => WorkOrderPolicy::class,
        Webhook::class          => WebhookPolicy::class,
        Conversation::class     => ConversationPolicy::class,
        WaTemplate::class       => WaTemplatePolicy::class,
        BroadcastCampaign::class => BroadcastCampaignPolicy::class,
        EmailTemplate::class    => EmailTemplatePolicy::class,
        EmailSegment::class     => EmailSegmentPolicy::class,
        Subscriber::class       => SubscriberPolicy::class,
        PosOrder::class         => PosOrderPolicy::class,
        PosSession::class       => PosSessionPolicy::class,
        PosReturn::class        => PosReturnPolicy::class,
        Shift::class            => ShiftPolicy::class,
        // Procurement (Achats)
        PurchaseOrder::class    => PurchaseOrderPolicy::class,
        SupplierQuote::class    => SupplierQuotePolicy::class,
        // Quality
        QualityIssue::class     => QualityIssuePolicy::class,
        // Ecommerce
        \Modules\Ecommerce\Models\Product::class => EcommerceProductPolicy::class,
        Cart::class             => CartPolicy::class,
        Store::class            => StorePolicy::class,
        Review::class           => ReviewPolicy::class,
        Rfq::class              => RfqPolicy::class,
        Coupon::class           => CouponPolicy::class,
        Promotion::class        => PromotionPolicy::class,
        // Manufacturing
        Bom::class              => BomPolicy::class,
        CapacityAllocation::class => CapacityAllocationPolicy::class,
        Workcenter::class       => WorkcenterPolicy::class,
        Routing::class          => RoutingPolicy::class,
        CapacityConstraint::class => CapacityConstraintPolicy::class,
        CapacityAllocation::class => CapacityAllocationPolicy::class,
        // Validation
        ApprovalRequest::class => ApprovalRequestPolicy::class,
        // Timesheets
        TimesheetEntry::class => TimesheetEntryPolicy::class,
        // PLM
        PLMProduct::class => PLMProductPolicy::class,
        BillOfMaterial::class => PLMBOMPolicy::class,
        ProductChange::class => ChangePolicy::class,
        // Workflow Automation
        Workflow::class => WorkflowPolicy::class,
        // RBAC
        Role::class             => RolePolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(RolePermissionService::class, function () {
            return new RolePermissionService();
        });
    }

    public function boot(): void
    {
        // Audit authentication-security events (login / logout / failed / lockout)
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\AuthEventSubscriber::class);

        // Drop "dangling prefix" route names — routes that inherited only a group
        // ->name('prefix.') with no per-route leaf name end up sharing a bogus name
        // (e.g. 'api.', 'accounting.'). Such names are not reference-able and break
        // route:cache (which requires unique names). Clearing them makes the routes
        // truly unnamed, which route caching allows. Runs after every module's
        // routes are registered.
        $this->app->booted(function () {
            foreach ($this->app['router']->getRoutes() as $route) {
                $name = $route->getName();
                if ($name !== null && str_ends_with($name, '.')) {
                    // Make the route truly unnamed: getName() then returns null,
                    // which route:cache serialization allows (no name collision).
                    unset($route->action['as']);
                }
            }
        });

        // Super-admins bypass every Gate check
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->ip() . '|' . strtolower((string) $request->input('email', ''))
            );
        });

        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('sync', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Semantic limiters used by module routes (Core and others). Keyed by
        // authenticated user when available, falling back to client IP.
        $byUser = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('simple_get', function (Request $request) use ($byUser) {
            return Limit::perMinute(120)->by($byUser($request));
        });

        RateLimiter::for('complex_get', function (Request $request) use ($byUser) {
            return Limit::perMinute(60)->by($byUser($request));
        });

        RateLimiter::for('create_post', function (Request $request) use ($byUser) {
            return Limit::perMinute(30)->by($byUser($request));
        });

        RateLimiter::for('expensive', function (Request $request) use ($byUser) {
            return Limit::perMinute(10)->by($byUser($request));
        });

        RateLimiter::for('secrets', function (Request $request) use ($byUser) {
            return Limit::perMinute(10)->by($byUser($request));
        });

        // Inbound webhooks (e.g. Accounting open-banking). Keyed by IP since the
        // caller is an external service, not an authenticated user.
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
