<?php

namespace App\Passkeys;

use Illuminate\Support\Facades\Config;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Webauthn\PublicKeyCredentialRpEntity;

class GenerateAdminRegistrationOptions extends GenerateRegistrationOptions
{
    protected function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            name: Config::string('passkeys.relying_party_name'),
            id: Config::string('passkeys.relying_party_id'),
        );
    }
}
