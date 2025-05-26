<div class="overflow-hidden rounded-t-lg shadow bg-white">
    <table class="min-w-full">
      <thead class="bg-blue-600">
        <tr>
          <th
            scope="col"
            class="px-2 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-lg"
          >
            Position
          </th>
          <th
            scope="col"
            class="px-2 py-3 text-xs text-right font-semibold uppercase tracking-wider text-white last:rounded-tr-lg"
          >
            Property
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-100">
        @forelse($rows as $row)
          <tr @class([
              'bg-gray-50'      => $loop->odd,
              'bg-white'        => $loop->even,
              'hover:bg-gray-100' => true,
              'border-l-4'      => true,
          ])>
            <td class="px-2 py-4 whitespace-normal text-sm font-medium text-gray-900">
              {{ $row['label'] }}
            </td>
            <td class="px-2 py-4 whitespace-normal text-sm text-right text-gray-700">
              {!! $row['value'] !!}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="2" class="px-6 py-4 text-center text-gray-400">
              No asset information available.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  

