{{--
    Partial original de Breeze — Formulario de eliminación de cuenta.

    NOTA: Este archivo NO se usa en la aplicación. La funcionalidad de eliminación
    de cuenta está implementada directamente en profile/edit.blade.php con un
    modal Bootstrap 5 (sin Tailwind ni componentes Breeze).

    Se mantiene como referencia del scaffolding original de Laravel Breeze.
    Usa clases de Tailwind CSS y componentes Blade de Breeze:
      - x-danger-button, x-modal, x-secondary-button, x-input-label, x-text-input

    Funcionalidad original:
      - Botón "Delete Account" que abre un modal de confirmación
      - Modal pide contraseña para confirmar
      - DELETE a profile.destroy (ProfileController@destroy)
      - Error bag 'userDeletion' para errores separados

    Relacionado con:
      - profile/edit.blade.php → vista real con modal Bootstrap de eliminación
      - ProfileController@destroy → procesa la eliminación de la cuenta
--}}
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    {{-- Botón que abre el modal de confirmación (componente Breeze) --}}
    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    {{-- Modal de confirmación (componente Breeze con Tailwind) --}}
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            {{-- Campo de contraseña para confirmar eliminación --}}
            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            {{-- Botones: Cancelar + Eliminar --}}
            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
