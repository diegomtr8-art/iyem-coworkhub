<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Tus datos
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Tu nombre. El correo se cambia en «Mi seguridad», con verificación
                de la dirección nueva.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Nombre" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- El correo ya no se edita aquí: cambiarlo sin verificar la
                 dirección nueva es justo lo que la Fase 4.C vino a cerrar. Se
                 muestra de solo lectura con enlace a «Mi seguridad». -->
            <div>
                <InputLabel value="Correo electrónico" />
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <span class="text-gray-700">{{ user.email }}</span>
                    <Link :href="route('seguridad') + '#correo'" class="text-sm text-gray-600 underline hover:text-gray-900">
                        Cambiar en Mi seguridad
                    </Link>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
