<?php



namespace App\Http\Controllers;



use App\Models\Lead;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;



class LeadController extends Controller

{

    public function create()

    {

        return view('leads.create');

    }



    public function store(Request $request)

    {

        $rules = [

            'name' => 'required|string|max:255',

            'email' => 'required|email|max:255',

            'phone' => 'required|string|max:20',

            'subject' => 'nullable|string|max:255',

            'message' => 'nullable|string|max:5000',

            'budget' => 'nullable|string|max:100',

            'preferred_contact' => 'nullable|in:email,phone,whatsapp',

            'service_slug' => 'nullable|string|max:100',

            'design_slug' => 'nullable|string|max:100',

            'calculated_price' => 'nullable|numeric|min:0',

            'dimensions' => 'nullable|string|max:255',

            'unit_type' => 'nullable|string|max:50',

            'type' => 'nullable|in:custom_order,service_inquiry,order_now,professional_application,railing_quotation',

            'company' => 'nullable|string|max:255',

            'role' => 'nullable|string|max:100',

            'city' => 'nullable|string|max:120',

            'website' => 'nullable|string|max:500',

            'gst_number' => 'nullable|string|max:50',

            'years_in_business' => 'nullable|string|max:50',

            'interest_areas' => 'nullable|array',

            'interest_areas.*' => 'string|max:50',

            'customer_type' => 'nullable|string|max:50',

            'usage' => 'nullable|string|max:50',

            'railing_category' => 'nullable|string|max:50',

            'layout_shape' => 'nullable|string|max:50',

            'material' => 'nullable|string|max:50',

            'finish' => 'nullable|string|max:50',

            'running_feet' => 'nullable|numeric|min:0|max:500',

            'step_count' => 'nullable|integer|min:0|max:100',

            'railing_height' => 'nullable|string|max:50',

            'project_location' => 'nullable|string|max:255',

            'timeline' => 'nullable|string|max:50',

            'whatsapp' => 'nullable|string|max:20',

            'drawing' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',

        ];



        if ($request->input('type') === 'professional_application') {

            $rules['company'] = 'required|string|max:255';

            $rules['role'] = 'required|string|max:100';

            $rules['city'] = 'required|string|max:120';

            $rules['message'] = 'required|string|max:5000';

        }



        if ($request->input('type') === 'railing_quotation') {

            $rules['customer_type'] = 'required|string|max:50';

            $rules['usage'] = 'required|string|max:50';

            $rules['railing_category'] = 'required|string|max:50';

            $rules['layout_shape'] = 'required|string|max:50';

            $rules['material'] = 'required|string|max:50';

            $rules['finish'] = 'required|string|max:50';

            $rules['running_feet'] = 'required|numeric|min:0.5|max:500';

            $rules['railing_height'] = 'required|string|max:50';

            $rules['project_location'] = 'required|string|max:255';

            $rules['timeline'] = 'required|string|max:50';

        }



        if (! in_array($request->input('type'), ['professional_application', 'railing_quotation'], true)) {

            $rules['message'] = 'required|string|max:5000';

        }



        $validated = $request->validate($rules);



        $type = $validated['type'] ?? ($request->filled('calculated_price') ? 'order_now' : 'custom_order');



        $extras = [

            'company' => $validated['company'] ?? null,

            'role' => $validated['role'] ?? null,

            'city' => $validated['city'] ?? null,

            'website' => $validated['website'] ?? null,

            'gst_number' => $validated['gst_number'] ?? null,

            'years_in_business' => $validated['years_in_business'] ?? null,

            'interest_areas' => $validated['interest_areas'] ?? [],

            'customer_type' => $validated['customer_type'] ?? null,

            'usage' => $validated['usage'] ?? null,

            'railing_category' => $validated['railing_category'] ?? null,

            'layout_shape' => $validated['layout_shape'] ?? null,

            'material' => $validated['material'] ?? null,

            'finish' => $validated['finish'] ?? null,

            'running_feet' => $validated['running_feet'] ?? null,

            'step_count' => $validated['step_count'] ?? null,

            'railing_height' => $validated['railing_height'] ?? null,

            'project_location' => $validated['project_location'] ?? null,

            'timeline' => $validated['timeline'] ?? null,

            'whatsapp' => $validated['whatsapp'] ?? null,

        ];



        unset(

            $validated['type'], $validated['company'], $validated['role'], $validated['city'],

            $validated['website'], $validated['gst_number'], $validated['years_in_business'], $validated['interest_areas'],

            $validated['customer_type'], $validated['usage'], $validated['railing_category'], $validated['layout_shape'],

            $validated['material'], $validated['finish'], $validated['running_feet'], $validated['step_count'],

            $validated['railing_height'], $validated['project_location'], $validated['timeline'], $validated['whatsapp'],

            $validated['drawing']

        );



        $drawingPath = null;

        if ($request->hasFile('drawing')) {
            $file = $request->file('drawing');
            $drawingPath = $file->store('lead-uploads', 'local');

            MediaFile::create([
                'disk' => 'local',
                'path' => $drawingPath,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'is_private' => true,
            ]);
        }



        if ($type === 'professional_application') {

            $header = collect([

                'Company' => $extras['company'],

                'Role' => $extras['role'],

                'City' => $extras['city'],

                'Website' => $extras['website'],

                'GST / Registration' => $extras['gst_number'],

                'Years in business' => $extras['years_in_business'],

                'Interest areas' => $extras['interest_areas'] ? implode(', ', $extras['interest_areas']) : null,

                'Project volume' => $validated['budget'] ?? null,

            ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");



            $validated['message'] = $header . "\n\n---\n\n" . ($validated['message'] ?? '');

        }



        if ($type === 'railing_quotation') {

            $formLabels = config('railings.form', []);

            $label = fn (string $group, ?string $key) => $key && isset($formLabels[$group][$key])

                ? $formLabels[$group][$key]

                : $key;



            $header = collect([

                'Customer type' => $label('customer_types', $extras['customer_type']),

                'Usage' => $label('usage', $extras['usage']),

                'Railing category' => $label('railing_categories', $extras['railing_category']),

                'Layout shape' => $label('layout_shapes', $extras['layout_shape']),

                'Material' => $label('materials', $extras['material']),

                'Finish' => $label('finishes', $extras['finish']),

                'Running feet' => $extras['running_feet'] ? $extras['running_feet'] . ' ft' : null,

                'Number of steps' => $extras['step_count'],

                'Railing height' => $label('heights', $extras['railing_height']),

                'Project location' => $extras['project_location'],

                'Timeline' => $label('timelines', $extras['timeline']),

                'WhatsApp' => $extras['whatsapp'],

                'Drawing upload' => $drawingPath ? 'Attached (admin only)' : null,

            ])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");



            $notes = trim($validated['message'] ?? '') ?: 'No additional notes.';

            $validated['message'] = $header . "\n\n---\n\n" . $notes;

            $validated['dimensions'] = ($extras['running_feet'] ?? '') . ' running ft';

            $validated['budget'] = $label('timelines', $extras['timeline']);

            $validated['service_slug'] = $validated['service_slug'] ?? 'railings';

        }



        $lead = Lead::create([
            ...$validated,
            'type' => $type,
            'status' => 'new',
            'metadata' => array_filter([
                ...$extras,
                'drawing_path' => $drawingPath,
                'drawing_filename' => $request->hasFile('drawing')
                    ? $request->file('drawing')->getClientOriginalName()
                    : null,
            ]),
            'admin_notes' => null,
        ]);



        $adminEmail = config('services.admin_email');

        if ($adminEmail) {

            $details = "New {$lead->typeLabel()} from {$lead->name} ({$lead->email}).";

            if ($lead->service_slug) {

                $details .= "\nService: {$lead->service_slug}";

            }

            if ($lead->calculated_price) {

                $details .= "\nEstimated price: ₹" . number_format($lead->calculated_price, 0);

            }

            if ($drawingPath) {
                $details .= "\nDrawing: attached (view in admin panel)";
            }

            $details .= "\n\n{$lead->message}";



            Mail::raw(

                $details,

                fn ($message) => $message->to($adminEmail)->subject("New {$lead->typeLabel()} — Vyomika Atelier LLP")

            );

        }



        $success = match ($type) {

            'professional_application' => 'Thank you! Your professional application was received. We will review and contact you within 2–3 business days.',

            'railing_quotation' => 'Thank you! Your railing quotation request was received. Our studio team will contact you within one business day.',

            default => 'Thank you! We received your request and will contact you soon.',

        };



        return back()->with('success', $success);

    }

}

