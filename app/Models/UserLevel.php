<?php

namespace App\Models;

use App\Domains\Identity\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'xp',
        'level',
        'bar_messages_today',
        'bar_reactions_today',
        'last_activity_date',
    ];

    protected $casts = [
        'xp' => 'integer',
        'level' => 'integer',
        'bar_messages_today' => 'integer',
        'bar_reactions_today' => 'integer',
        'last_activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the current tier based on level
     */
    public function getTier(): array
    {
        $tiers = config('levels.tiers');
        $currentTier = $tiers[0];
        $nextTier = null;

        foreach ($tiers as $index => $tier) {
            if ($this->level >= $tier['min_level']) {
                $currentTier = $tier;
                $nextTier = $tiers[$index + 1] ?? null;
            }
        }

        return [
            'name' => $currentTier['name'],
            'name_en' => $currentTier['name_en'],
            'icon' => $currentTier['icon'],
            'min_level' => $currentTier['min_level'],
            'next_tier' => $nextTier ? [
                'name' => $nextTier['name'],
                'name_en' => $nextTier['name_en'],
                'level_required' => $nextTier['min_level'],
            ] : null,
        ];
    }

    /**
     * Get bar creation perks for current level
     */
    public function getBarPerks(): array
    {
        $perks = config('levels.bar_perks');
        $currentPerks = ['max_bars' => 0, 'max_members' => 0];

        foreach ($perks as $levelRequired => $perk) {
            if ($this->level >= $levelRequired) {
                $currentPerks = $perk;
            }
        }

        return $currentPerks;
    }

    /**
     * Calculate XP needed for next level
     */
    public function getXpForNextLevel(): int
    {
        $formula = config('levels.xp_formula');
        
        if ($formula['type'] === 'flat') {
            return $formula['base_xp'];
        }

        return (int) ($formula['base_xp'] * pow($this->level + 1, $formula['multiplier']));
    }

    /**
     * Get XP progress to next level (0-100%)
     */
    public function getProgressToNextLevel(): float
    {
        $currentLevelXp = $this->getXpForLevel($this->level);
        $nextLevelXp = $this->getXpForLevel($this->level + 1);
        $xpInCurrentLevel = $this->xp - $currentLevelXp;
        $xpNeeded = $nextLevelXp - $currentLevelXp;

        if ($xpNeeded <= 0) {
            return 100;
        }

        return min(100, ($xpInCurrentLevel / $xpNeeded) * 100);
    }

    /**
     * Calculate total XP needed to reach a specific level
     */
    public function getXpForLevel(int $level): int
    {
        $formula = config('levels.xp_formula');
        $totalXp = 0;

        for ($i = 1; $i < $level; $i++) {
            if ($formula['type'] === 'flat') {
                $totalXp += $formula['base_xp'];
            } else {
                $totalXp += (int) ($formula['base_xp'] * pow($i, $formula['multiplier']));
            }
        }

        return $totalXp;
    }

    /**
     * Add XP and level up if needed
     */
    public function addXp(int $amount): bool
    {
        $this->xp += $amount;
        $leveled = false;
        $maxLevel = config('levels.max_level');

        while ($this->level < $maxLevel && $this->xp >= $this->getXpForLevel($this->level + 1)) {
            $this->level++;
            $leveled = true;
        }

        $this->save();

        return $leveled;
    }

    /**
     * Check and reset daily caps if needed
     */
    public function checkDailyCaps(): void
    {
        if ($this->last_activity_date !== today()) {
            $this->bar_messages_today = 0;
            $this->bar_reactions_today = 0;
            $this->last_activity_date = today();
            $this->save();
        }
    }

    /**
     * Check if user can earn XP for bar message
     */
    public function canEarnMessageXp(): bool
    {
        $this->checkDailyCaps();
        return $this->bar_messages_today < config('levels.xp_rewards.bar_message_daily_cap');
    }

    /**
     * Check if user can earn XP for bar reaction
     */
    public function canEarnReactionXp(): bool
    {
        $this->checkDailyCaps();
        return $this->bar_reactions_today < config('levels.xp_rewards.bar_reaction_daily_cap');
    }
}
