<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = $this->filteredContacts($request);

        return view('contacts.index', compact('contacts'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário digita/filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $contacts = $this->filteredContacts($request);

        return view('contacts._results', compact('contacts'));
    }

    private function filteredContacts(Request $request)
    {
        $query = Contact::with('assignedTo')
            ->orderByDesc('created_at');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('company_name', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        return $query->paginate(30)->withQueryString();
    }

    public function create()
    {
        return view('contacts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'whatsapp'     => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'source'       => 'required|in:' . implode(',', array_keys(Contact::$sources)),
            'notes'        => 'nullable|string',
        ]);

        $data['status']     = 'ativo';
        $data['created_by'] = Auth::id();

        $contact = Contact::create($data);

        if ($request->boolean('create_opportunity')) {
            return redirect()->route('opportunities.create', ['contact_id' => $contact->id]);
        }

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Contato cadastrado com sucesso.');
    }

    public function show(Contact $contact)
    {
        $contact->load('opportunities.client', 'clients');
        return view('contacts.show', compact('contact'));
    }

    public function edit(Contact $contact)
    {
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'whatsapp'     => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'source'       => 'required|in:' . implode(',', array_keys(Contact::$sources)),
            'status'       => 'required|in:' . implode(',', array_keys(Contact::$statuses)),
            'notes'        => 'nullable|string',
        ]);

        $contact->update($data);

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Contato atualizado.');
    }

    // Delete de verdade — exceção à regra geral de "nunca deletar contato,
    // usar status inativo" (ver CLAUDE.md), só permitida quando o contato
    // não tem NENHUMA associação real (ver Contact::blockingAssociations()).
    // Existe pra limpar duplicatas cadastradas por engano.
    public function destroy(Contact $contact)
    {
        $blocking = $contact->blockingAssociations();

        if (!empty($blocking)) {
            $reasons = collect($blocking)->map(fn ($count, $label) => "{$count} {$label}")->implode(', ');
            return back()->with('error', "Não é possível excluir — este contato tem: {$reasons}. Use o status \"Inativo\" em vez de excluir.");
        }

        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contato excluído.');
    }
}
