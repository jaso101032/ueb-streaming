<x-app-layout>
    @php
$props=['name'=>old('name',$user->name),'email'=>old('email',$user->email),'updateUrl'=>route('profile.update'),
'passwordUrl'=>route('password.update'),'deleteUrl'=>route('profile.destroy'),'verifyUrl'=>route('verification.send'),
'unverified'=>$user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail(),
'passwordErrors'=>$errors->getBag('updatePassword')->all(),'deleteErrors'=>$errors->getBag('userDeletion')->all()];
    @endphp
    <div data-react-page="Profile" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
