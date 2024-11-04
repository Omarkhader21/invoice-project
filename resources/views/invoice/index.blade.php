<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoices') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="flex flex-col">
                <div class="-m-1.5"> <!-- Remove overflow-x-auto here -->
                    <div class="p-1.5 min-w-full inline-block align-middle">
                        <div class="border rounded-lg divide-y divide-gray-200">
                            <div class="overflow-hidden">
                                <table class="w-full min-w-full divide-y divide-gray-200"> <!-- Add w-full here -->
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                UUID
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Issue Date
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Invoice Type
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Registration Name
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Amount
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Amount
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Exclusive Amount
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Inclusive Amount
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Sent to Fawtara
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($paginatedInvoices as $key => $invoice)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $invoice->{'uuid'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ \Carbon\Carbon::parse($invoice->{'issuedate'})->format('F j, Y') }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'invoicetypecode'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'registrationname'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'amount'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'taxamount'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'taxexclusiveamount'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $invoice->{'taxinclusiveamount'} }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-center">
                                                    @if($invoice->{'sent-to-fawtara'} == '1')
                                                        <i class="fa-solid fa-circle-check text-xl text-green-700"></i>
                                                    @else
                                                        <i class="fa-solid fa-circle-xmark text-xl text-red-700"></i>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                                    <a href="{{ route('invoice.show', $invoice->uuid) }}"
                                                        class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-blue-600 hover:text-blue-800 focus:outline-none focus:text-blue-800 disabled:opacity-50 disabled:pointer-events-none">Show</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="py-1 px-4">
                                <nav class="flex items-center space-x-1" aria-label="Pagination">
                                    {{ $paginatedInvoices->links() }}
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
