<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { useForm, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import AuthController from '@/actions/App/Http/Controllers/AuthController';
import { computed } from 'vue';

const props = defineProps<{
    status?: string;
}>();

// Access page props for flash messages
const page = usePage();

// Get flash messages safely
const flashSuccess = computed(() => {
    const flash = page.props.flash as any;
    return flash?.success;
});

// Form for Fabi login
const form = useForm({
    email: '',
    password: '',
    remember: false
});

const submitForm = () => {
    form.post(AuthController.login.url(), {
        onFinish: () => {
            form.password = '';
        }
    });
};
</script>

<template>
    <AuthBase
        title="Đăng nhập vào hệ thống"
        description="Sử dụng tài khoản Fabi/iPos để đăng nhập"
    >
        <Head title="Đăng nhập" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <!-- Success message from flash -->
        <div
            v-if="flashSuccess"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ flashSuccess }}
        </div>

        <form
            @submit.prevent="submitForm"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Địa chỉ email Fabi</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="tranhoanggiang5@gmail.com"
                        :class="{ 'border-red-500': form.errors.email }"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mật khẩu</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Nhập mật khẩu"
                        :class="{ 'border-red-500': form.errors.password }"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox 
                            id="remember" 
                            v-model:checked="form.remember"
                            name="remember" 
                            :tabindex="3" 
                        />
                        <span>Ghi nhớ đăng nhập</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="form.processing"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                    />
                    <span v-if="form.processing">Đang đăng nhập...</span>
                    <span v-else>Đăng nhập</span>
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Hệ thống phân tích thu chi cho ZangTee
            </div>
        </form>
    </AuthBase>
</template>
