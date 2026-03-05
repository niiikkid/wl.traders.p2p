<script setup>
import ThemeMarquee from "@/Components/ThemeMarquee.vue";
</script>

<template>
    <div>
<!--        <ThemeMarquee />-->
        <div class="min-h-screen bg-base-200 flex items-center justify-center px-4 py-8">
            <div class="w-full sm:max-w-md">
                <div class="flex items-end gap-3 justify-center mb-6">
                    <div>
                        <div class="text-4xl font-extrabold tracking-tight">{{$page.props.app.name}}</div>
                        <div class="text-xs font-medium text-base-content/70 text-center">Надежный процессинг</div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-xl border border-base-300 overflow-hidden sm:rounded-plate">
                    <div class="card-body p-6 sm:p-8">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
