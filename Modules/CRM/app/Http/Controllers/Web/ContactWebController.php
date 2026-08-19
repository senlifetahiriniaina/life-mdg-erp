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
        // Chantier 19: this Inertia web page rendered contacts straight from the model with
        // zero tenant scoping — a second, entirely separate code path from ContactController's
        // API index() (also fixed this chantier), reachable via the normal /crm/contacts page
        // and previously leaking every company's contact list regardless of which fix landed
        // on the API side.
        $contacts = Contact::with('account')
            ->where('company_id', $request->user()->company_id)
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

    public function show(Request $request, Contact $contact): Response
    {
        // Chantier 19: unlike the API's show(), this web action had zero authorize() call at
        // all — any authenticated user could open any other company's contact detail page by
        // guessing its id.
        $this->authorize('view', $contact);

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
        // Chantier 19: same gap as index() above — zero tenant scoping on the web-rendered
        // leads list.
        $leads = Lead::query()
            ->where('company_id', $request->user()->company_id)
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
        // Chantier 19: same gap as index() above — zero tenant scoping on the web-rendered
        // accounts list.
        $accounts = Account::query()
            ->where('company_id', $request->user()->company_id)
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
        // Chantier 19: no authorize() call at all — any authenticated user could open any
        // other company's account edit form by guessing its id.
        $this->authorize('update', $account);

        return Inertia::render('CRM/Accounts/Form', ['account' => $account]);
    }

    public function storeAccount(Request $request)
    {
        $validated = $this->validateAccount($request);

        $account = Account::create(array_merge($validated, [
            'owner_id' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]));

        return redirect('/crm/accounts/'.$account->id.'/edit')->with('success', 'Account created.');
    }

    public function updateAccount(Request $request, Account $account)
    {
        // Chantier 19: no authorize() call at all — any authenticated user could submit this
        // form against any other company's account id.
        $this->authorize('update', $account);

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
