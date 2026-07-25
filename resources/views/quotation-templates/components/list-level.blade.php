<{{ $tag }} class="quotation-item-list level-{{ $depth }}"@if($tag === 'ol' && $depth === 2) type="a"@endif>@foreach($nodes as $node)<li>{{ $node['content'] }}@if($node['children'] !== [])@include('quotation-templates.components.list-level', ['nodes' => $node['children'], 'tag' => $tag, 'depth' => $depth + 1])@endif</li>@endforeach</{{ $tag }}>

