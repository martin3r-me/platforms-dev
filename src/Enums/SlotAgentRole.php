<?php

namespace Platform\Dev\Enums;

/**
 * Rolle eines Board-Slots in der Zustandsmaschine des autonomen Workers.
 * Macht die Board-Spalten zur sichtbaren Worker-Pipeline.
 */
enum SlotAgentRole: string
{
    case Ready = 'ready';       // Worker HOLT Issues von hier
    case Working = 'working';   // Worker arbeitet dran (schiebt Issue hierher)
    case Human = 'human';       // Human-in-the-Loop: geparkt für Entscheidung/Antwort
    case Done = 'done';         // Fertig/verifiziert

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready (Worker holt)',
            self::Working => 'In Arbeit (Worker)',
            self::Human => 'Human-in-the-Loop',
            self::Done => 'Fertig',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ready => 'heroicon-o-inbox-arrow-down',
            self::Working => 'heroicon-o-cpu-chip',
            self::Human => 'heroicon-o-hand-raised',
            self::Done => 'heroicon-o-check-circle',
        };
    }

    /** [value => label] inkl. „keine Rolle" für Selects. */
    public static function options(): array
    {
        $out = ['' => '— keine (rein menschlich) —'];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
