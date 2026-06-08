<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        abort_if(!auth()->user()->can('customer_view'), 403);
        if ($request->ajax()) {
            $customers = Customer::latest()->get();
            return DataTables::of($customers)
                ->addIndexColumn()
                ->addColumn('name',       fn ($d) => $d->name)
                ->addColumn('phone',      fn ($d) => $d->phone)
                ->addColumn('address',    fn ($d) => $d->address)
                ->addColumn('created_at', fn ($d) => $d->created_at->format('d M, Y'))
                ->addColumn('action', function ($d) {
                    $editHref    = route('backend.admin.customers.edit', $d->id);
                    $deleteRoute = route('backend.admin.customers.destroy', $d->id);
                    $salesHref   = route('backend.admin.customers.orders', $d->id);
                    $formId      = 'del-' . $d->id;
                    $btnLabel    = $d->orders()->exists() ? 'Archive' : 'Delete';
                    $btnIcon     = $d->orders()->exists() ? 'fa-archive' : 'fa-trash';
                    $btnDisabled = ($d->id == 1) ? ' disabled' : '';

                    $html  = '<div class="btn-group">';
                    $html .= '<button type="button" class="btn bg-gradient-primary btn-flat">Action</button>';
                    $html .= '<button type="button" class="btn bg-gradient-primary btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown">';
                    $html .= '<span class="sr-only">Toggle Dropdown</span></button>';
                    $html .= '<div class="dropdown-menu" role="menu">';

                    if (auth()->user()->can('customer_update')) {
                        $noClick = ($d->id == 1) ? ' onclick="event.preventDefault();"' : '';
                        $html   .= '<a class="dropdown-item" href="' . $editHref . '"' . $noClick . '>';
                        $html   .= '<i class="fas fa-edit"></i> Edit</a>';
                        $html   .= '<div class="dropdown-divider"></div>';
                    }

                    if (auth()->user()->can('customer_delete')) {
                        $html .= '<form id="' . $formId . '" action="' . $deleteRoute . '" method="POST" style="display:none;">';
                        $html .= csrf_field() . method_field('DELETE') . '</form>';
                        $html .= '<button type="button"' . $btnDisabled . ' class="dropdown-item"';
                        $html .= ' onclick="if(confirm(\'' . $btnLabel . ' this customer?\')) document.getElementById(\'' . $formId . '\').submit()">';
                        $html .= '<i class="fas ' . $btnIcon . '"></i> ' . $btnLabel . '</button>';
                        $html .= '<div class="dropdown-divider"></div>';
                    }

                    if (auth()->user()->can('customer_sales')) {
                        $html .= '<a class="dropdown-item" href="' . $salesHref . '">';
                        $html .= '<i class="fas fa-cart-plus"></i> Sales</a>';
                    }

                    $html .= '</div></div>';
                    return $html;
                })
                ->rawColumns(['name', 'phone', 'address', 'created_at', 'action'])
                ->toJson();
        }
        return view('backend.customers.index');
    }

    public function create()
    {
        abort_if(!auth()->user()->can('customer_create'), 403);
        return view('backend.customers.create');
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->can('customer_create'), 403);

        if ($request->wantsJson()) {
            $request->validate(['name' => 'required|string']);
            $customer = Customer::create(['name' => $request->name]);
            return response()->json($customer);
        }

        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:customers,phone',
            'address' => 'nullable|string|max:255',
        ]);

        Customer::create($request->only(['name', 'phone', 'address']));
        session()->flash('success', 'Customer created successfully.');
        return to_route('backend.admin.customers.index');
    }

    public function show(Customer $customer) {}

    public function edit($id)
    {
        abort_if(!auth()->user()->can('customer_update'), 403);
        $customer = Customer::findOrFail($id);
        return view('backend.customers.edit', compact('customer'));
    }

    public function update(Request $request, $id)
    {
        abort_if(!auth()->user()->can('customer_update'), 403);
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:customers,phone,' . $customer->id,
            'address' => 'nullable|string|max:255',
        ]);

        $customer->update($request->only(['name', 'phone', 'address']));
        session()->flash('success', 'Customer updated successfully.');
        return to_route('backend.admin.customers.index');
    }

    /**
     * FIX: SoftDelete вместо hard delete.
     * Риск: cascadeOnDelete на orders/order_products/order_transactions
     * уничтожал всю финансовую историю при удалении клиента.
     * Теперь: клиент архивируется (deleted_at заполняется),
     * все заказы и транзакции остаются нетронутыми.
     */
    public function destroy($id)
    {
        abort_if(!auth()->user()->can('customer_delete'), 403);

        $customer = Customer::findOrFail($id);

        if ((int) $customer->id === 1) {
            session()->flash('error', 'Cannot delete the default Walk-in customer.');
            return to_route('backend.admin.customers.index');
        }

        $customer->delete(); // SoftDelete — deleted_at заполняется, история сохраняется

        session()->flash('success', 'Customer archived. Financial history preserved.');
        return to_route('backend.admin.customers.index');
    }

    public function getCustomers(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(Customer::latest()->get());
        }
    }

    public function orders($id)
    {
        $customer = Customer::findOrFail($id);
        $orders   = $customer->orders()->paginate(100);
        return view('backend.orders.index', compact('orders'));
    }
}
