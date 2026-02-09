<?php

namespace App\Services\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Services\ActionService\Edit\ToTgEditService;

class WhatsAppEditService extends ToTgEditService
{
    protected string $source = 'whatsapp';

    protected string $typeMessage = 'incoming';

    public function __construct(WhatsAppUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * WhatsApp does not support message editing webhooks.
     * This is a placeholder for potential future support.
     *
     * @return void
     */
    public function handleUpdate(): void
    {
        // WhatsApp Cloud API does not send edit webhooks
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function editMessageCaption(): void
    {
        //
    }
}
