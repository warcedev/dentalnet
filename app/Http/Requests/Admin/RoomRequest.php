<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room instanceof Room ? $this->user()->can('update', $room) : $this->user()->can('create', Room::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('rooms', 'name')->ignore($room instanceof Room ? $room->id : null)],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }
}
