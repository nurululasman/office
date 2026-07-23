@if($terms->isNotEmpty())<ul class="terms">@foreach($terms as $term)<li>{{ $term->content }}</li>@endforeach</ul>@endif
