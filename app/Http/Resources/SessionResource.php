<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'content_type' => 'session',
            'title' => $this->title,
            'can_view_error' => $this->canViewError(),
            'auth_has_read' => $this->read,
            'date' => $this->date,
            'duration' => $this->duration,
            'description' => $this->description,
            'created_at' => $this->created_at,

            //   'user_has_access' => $this->user_has_access,
            'is_finished' => $this->isFinished(),
            'is_started'=>(time() > $this->date) ,
            // 'status' => $this->status,
            // 'order' => $this->order,
            // Host-only values: keys stay for the app, values only for the session creator.
            'moderator_secret' => $this->isHostViewer() ? $this->moderator_secret : null,

            'link' => $this->link,
            'join_link' => (apiAuth()) ? $this->getJoinLink() : null,
            'can_join'=>(apiAuth() and !$this->isFinished() and time() > $this->date ) ,
            'session_api' => $this->session_api,
            'zoom_start_link' => $this->isHostViewer() ? $this->zoom_start_link : null,
            // 'session_api' => $this->session_api,
            'api_secret' => $this->api_secret,
            'check_previous_parts' => $this->check_previous_parts,
            'access_after_day' => $this->access_after_day,

            // 'updated_at' => $this->updated_at,
            'agora_settings ' => $this->agora_settings,

        ];

    }

    private function isHostViewer(): bool
    {
        $user = apiAuth();

        return !empty($user) and ($user->id == $this->creator_id or (!empty($this->webinar) and $user->id == $this->webinar->teacher_id));
    }
}
