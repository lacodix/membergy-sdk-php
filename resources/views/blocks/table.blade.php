<div class="membergy-table">
    <table>
        @if ($block->hasHeader)
            <thead>
                <tr>
                    @foreach ($block->headers as $cell)
                        <th scope="col">{{ $cell }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @foreach ($block->rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
