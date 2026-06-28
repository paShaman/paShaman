<div style="display: flex; align-items: center; gap: 8px;">
    <img src="{{ $avatarUrl }}"
         alt="{{ $name }}"
         style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb;"
         onerror="this.style.display='none';" />
    <span>{{ $name }}</span>
</div>