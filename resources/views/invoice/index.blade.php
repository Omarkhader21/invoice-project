<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoices') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-12xl mx-auto py-12 sm:px-6 lg:px-12">
            <div class="flex flex-col">
                <div class="-m-1.5">
                    <div class="p-1.5 min-w-full inline-block align-middle">
                        <div class="border rounded-lg divide-y divide-gray-200">
                            <div class="overflow-hidden">
                                <table class="w-full min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                UUID</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Issue Date</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Invoice Code</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Invoice Type</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Registration Name</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Amount</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Amount</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Exclusive Amount</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Tax Inclusive Amount</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">
                                                Sent to Fawtara</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                                Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($paginatedInvoices as $key => $invoice)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{
                                                $invoice->{'uuid'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                \Carbon\Carbon::parse($invoice->{'issuedate'})->format('F j, Y') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'invoicetypecode'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'invoice_type'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'registrationname'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'amount'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'taxamount'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'taxexclusiveamount'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{
                                                $invoice->{'taxinclusiveamount'} }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-center">
                                                @if ($invoice->{'sent_to_fawtara'} == '1')
                                                <i class="fa-solid fa-circle-check text-xl text-green-700"></i>
                                                @else
                                                <i class="fa-solid fa-circle-xmark text-xl text-red-700"></i>
                                                @endif
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium flex space-x-2">
                                                <a href="{{ route('invoice.show', $invoice->uuid) }}"
                                                    class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-500 text-white hover:bg-blue-600 focus:outline-none focus:bg-blue-700 px-4 py-2">
                                                    Show
                                                </a>
                                                @if($invoice->{'sent_to_fawtara'} == '1')
                                                <a href="{{ route('generate-qrcode', $invoice->uuid) }}"
                                                    class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-purple-500 text-white hover:bg-purple-600 focus:outline-none focus:bg-purple-700 px-4 py-2">
                                                    Generate QR Code
                                                </a>
                                                @endif
                                                @if($invoice->{'sent_to_fawtara'} == '0')
                                                <form action="{{ route('send-Invoice', $invoice->uuid) }}" method="POST"
                                                    class="inline-flex">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-green-500 text-white hover:bg-green-600 focus:outline-none focus:bg-green-700 px-4 py-2">
                                                        Send
                                                    </button>
                                                </form>
                                                @endif
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