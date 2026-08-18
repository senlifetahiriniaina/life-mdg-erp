<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\Lead;

class ContactWebController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::with('account')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'full_name' => trim("{$c->first_name} {$c->last_name}"),
                'email' => $c->email,
                'phone' => $c->phone,
                'job_title' => $c->job_title,
                'status' => $c->status,
                'account' => $c->account ? ['id' => $c->account->id, 'name' => $c->account->name] : null,
            ]);

        return Inertia::render('CRM/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Contact $contact): Response
    {
        $contact->load('account');

        return Inertia::render('CRM/Contacts/Show', [
            'contact' => [
                'id' => $contact->id,
                'full_name' => trim("{$contact->first_name} {$contact->last_name}"),
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'job_title' => $contact->job_title,
                'department' => $contact->department,
                'status' => $contact->status,
                'account' => $contact->account ? ['id' => $contact->account->id, 'name' => $contact->account->name] : null,
                'created_at' => $contact->created_at?->format('M d, Y'),
            ],
            'activities' => [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CRM/Contacts/Create');
    }

    public function leads(Request $request): Response
    {
        $leads = Lead::query()
            ->with(['owner:id,name', 'contact:id,first_name,last_name'])
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($l) => [
                'id' => $l->id,
                'title' => $l->title,
                'status' => $l->status,
                'source' => $l->source,
                'score' => $l->score,
                'owner' => $l->owner ? ['id' => $l->owner->id, 'name' => $l->owner->name] : null,
                'contact_name' => $l->contact ? trim("{$l->contact->first_name} {$l->contact->last_name}") : null,
            ]);

        return Inertia::render('CRM/Leads/Index', [
            'leads' => $leads,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function accounts(Request $request): Response
    {
        $accounts = Account::query()
            ->withCount('contacts')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->filled('industry'), fn ($q) => $q->where('industry', $request->industry))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'industry' => $a->industry,
                'email' => $a->email,
                'phone' => $a->phone,
                'website' => $a->website,
                'contacts_count' => $a->contacts_count,
            ]);

        return Inertia::render('CRM/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'industry']),
        ]);
    }

    /**
     * Chantier 10: Accounts/Form.vue (create/edit) was a real, fully-built Inertia form —
     * useForm().post('/crm/accounts') / .put('/crm/accounts/{id}') — but had no web route or
     * controller action of any kind, only the API-only apiResource under /api/v1/crm/accounts.
     * Wired directly onto the real Account model/validation (mirroring AccountController's
     * own store() validation), not a new backend concept.
     */
    public function createAccount(): Response
    {
        return Inertia::render('CRM/Accounts/Form');
    }

    public function editAccount(Account $account): Response
    {
        return Inertia::render('CRM/Accounts/Form', ['account' => $account]);
    }

    public function storeAccount(Request $request)
    {
        $validated = $this->validateAccount($request);

        $account = Account::create(array_merge($validated, ['owner_id' => $request->user()->id]));

        return redirect('/crm/accounts/'.$account->id.'/edit')->with('success', 'Account created.');
    }

    public function updateAccount(Request $request, Account $account)
    {
        $account->update($this->validateAccount($request));

        return redirect('/crm/accounts')->with('success', 'Account updated.');
    }

    private function validateAccount(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:prospect,customer,partner'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            // Accounts/Form.vue's field is `employees`; Account's real column is
            // employee_count — mapped explicitly below rather than left to silently drop.
            'employees' => ['nullable', 'integer', 'min:1'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        if (array_key_exists('employees', $validated)) {
            $validated['employee_count'] = $validated['employees'];
            unset($validated['employees']);
        }

        return $validated;
    }

    public function emailSequences(Request $request): Response
    {
        $sequences = EmailSequence::withCount(['enrollments', 'steps'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EmailSequence $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'status' => $s->status,
                'trigger_event' => $s->trigger_event,
                'steps_count' => $s->steps_count,
                'enrollments_count' => $s->enrollments_count,
            ]);

        return Inertia::render('CRM/EmailSequences/Index', [
            'sequences' => $sequences,
            'filters' => $request->only(['status']),
        ]);
    }

    public function callLogs(Request $request): Response
    {
        return Inertia::render('CRM/CallLogs/Index', [
            'filters' => $request->only(['direction', 'status', 'date_from', 'date_to']),
        ]);
    }
}
