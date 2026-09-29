{{-- Imagen del sorteo/décimo: real si existe, placeholder si no --}}
@php
    $lotteryImageModel = $lotteryImageModel ?? null;
    $lotteryImageSize = $lotteryImageSize ?? 80;
    $hasLotteryImage = $lotteryImageModel && $lotteryImageModel->hasImage();
    $lotteryImageUrl = $hasLotteryImage ? $lotteryImageModel->imageUrl() : null;
@endphp
<div class="lottery-ticket-preview {{ $lotteryImageClass ?? '' }}"
     style="width: {{ (int) $lotteryImageSize }}px; height: {{ (int) $lotteryImageSize }}px; border-radius: 8px; background-color: #e9ecef; @if($lotteryImageUrl) background-image: url('{{ $lotteryImageUrl }}'); background-size: cover; background-position: center; @endif display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;"
     title="{{ $lotteryImageModel->name ?? 'Sorteo' }}">
    @if(! $hasLotteryImage)
        <i class="ri-image-line text-muted" style="font-size: {{ max(16, (int) $lotteryImageSize / 3) }}px;"></i>
    @endif
</div>
