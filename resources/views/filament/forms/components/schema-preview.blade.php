<div class="space-y-2">
    <h3 class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
        Frontend Form Preview
    </h3>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-2 font-medium text-gray-950 dark:text-white">Label (Key)</th>
                    <th class="px-4 py-2 font-medium text-gray-950 dark:text-white">Type</th>
                    <th class="px-4 py-2 font-medium text-gray-950 dark:text-white">Required?</th>
                    <th class="px-4 py-2 font-medium text-gray-950 dark:text-white">Default</th>
                    <th class="px-4 py-2 font-medium text-gray-950 dark:text-white">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @php
                    // Use the state passed from parent component
                    $json = $schemaState ?? null;
                    // Ensure we handle both string (JSON) and array (already decoded)
                    $schema = is_string($json) ? json_decode($json, true) : $json;
                @endphp

                @if(is_array($schema) && count($schema) > 0)
                    @foreach($schema as $field)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-2">
                                <span class="font-medium">{{ $field['label'] ?? $field['key'] ?? '-' }}</span>
                                <div class="text-xs text-gray-500 font-mono">{{ $field['key'] ?? '' }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-400/10 dark:text-indigo-400 dark:ring-indigo-400/30">
                                    {{ $field['type'] ?? 'text' }}
                                </span>
                            </td>
                             <td class="px-4 py-2">
                                @if(!empty($field['required']))
                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30">
                                        Yes
                                    </span>
                                @else
                                    <span class="text-gray-400">Optional</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400 font-mono text-xs">
                                @if(isset($field['default']))
                                    @if(is_array($field['default']))
                                        {{ json_encode($field['default']) }}
                                    @elseif(is_bool($field['default']))
                                        {{ $field['default'] ? 'true' : 'false' }}
                                    @else
                                        {{ $field['default'] }}
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                             <td class="px-4 py-2 text-gray-500 dark:text-gray-400 text-xs max-w-xs truncate">
                                {{ $field['description'] ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 italic">
                            No valid schema defined. Enter JSON above to see preview.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
