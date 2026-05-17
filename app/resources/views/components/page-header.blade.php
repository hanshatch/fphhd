@props(['title', 'back' => null, 'action' => null, 'actionLabel' => null, 'actionRoute' => null])
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        @if($back)
        <a href="{{ $back }}" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white dark:bg-[#2a2a2a] border border-[#ababab]/30 text-[#878787] hover:text-[#373737] dark:hover:text-white transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        @endif
        <h1 class="text-xl font-bold text-[#373737] dark:text-white font-['Nunito']">{{ $title }}</h1>
    </div>
    @if($actionRoute)
    <a href="{{ $actionRoute }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-semibold rounded-xl transition-colors shadow-sm active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        {{ $actionLabel }}
    </a>
    @endif
</div>
