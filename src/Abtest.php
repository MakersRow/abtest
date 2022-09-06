<?php

namespace Apurbajnu\abtest;

use Apurbajnu\abtest\Events\ExperimentNewVisitor;
use Apurbajnu\abtest\Events\GoalCompleted;
use Apurbajnu\abtest\Exceptions\InvalidConfiguration;
use Apurbajnu\abtest\Models\Experiment;
use Apurbajnu\abtest\Models\Goal;
use Illuminate\Support\Collection;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class Abtest
{
    protected $experiments;

    const SESSION_KEY_EXPERIMENT = 'ab_testing_experiment';
    const SESSION_KEY_GOALS = 'ab_testing_goals';

    public function __construct()
    {
        $this->experiments = new Collection;
    }

    /**
     * Validates the config items and puts them into models.
     *
     * @return void
     */
    protected function start()
    {
        $configExperiments = config('ab-testing.experiments');
        $configGoals = config('ab-testing.goals');

        if (!count($configExperiments)) {
            throw InvalidConfiguration::noExperiment();
        }

        if (count($configExperiments) !== count(array_unique(array_keys($configExperiments)))) {
            throw InvalidConfiguration::experiment();
        }

        if (count($configGoals) !== count(array_unique($configGoals))) {
            throw InvalidConfiguration::goal();
        }

        foreach ($configExperiments as $nameExperiment => $configExperiment) {
            $this->experiments[] = $experiment = Experiment::with('goals')->firstOrCreate([
                'name' => $nameExperiment,
            ], [
                'visitors' => 0,
            ]);

            foreach ($configGoals as $configGoal) {
                $experiment->goals()->firstOrCreate([
                    'name' => $configGoal,
                ], [
                    'hit' => 0,
                ]);
            }
        }

        session([
            self::SESSION_KEY_GOALS => new Collection,
        ]);
    }

    /**
     * Triggers a new visitor. Picks a new experiment and saves it to the session.
     *
     * @return \Apurbajnu\abtest\Models\Experiment|void
     */
    public function pageView()
    {
        if (config('ab-testing.ignore_crawlers') && (new CrawlerDetect)->isCrawler()) {
            return;
        }

        if (session(self::SESSION_KEY_EXPERIMENT)) {
            return;
        }

        $this->start();
        $this->setNextExperiment();

        event(new ExperimentNewVisitor($this->getExperiment()));

        return $this->getExperiment();
    }

    /**
     * Calculates a new experiment and sets it to the session.
     *
     * @return void
     */
    protected function setNextExperiment()
    {
        $next = $this->getNextExperiment();
        $next->incrementVisitor();

        session([
            self::SESSION_KEY_EXPERIMENT => $next,
        ]);
    }

    /**
     * Calculates a new experiment.
     *
     * @return \Apurbajnu\abtest\Models\Experiment|null
     */
    protected function getNextExperiment()
    {
        $experiments = config('ab-testing.experiments');
        $arrayExpGroup = [];

        $sumPercentages = array_sum(array_map(function ($expProbability) {
            if (is_int($expProbability) || is_float($expProbability)) {
                return abs($expProbability);
            }
        }, $experiments));

        if ($sumPercentages < 99 || $sumPercentages > 100) {
            throw InvalidConfiguration::experimentsTotalPercentage();
        }

        foreach ($experiments as $expName => $expProbability) {
            for ($i = 0; $i < $expProbability; $i++) {
                $arrayExpGroup[] = $expName;
            }
        }

        $expNameSelected = $arrayExpGroup[array_rand($arrayExpGroup)];

        return Experiment::where('name', $expNameSelected)->first();
    }

    /**
     * Checks if the currently active experiment is the given one.
     *
     * @param  string  $name  The experiments name
     * @return bool
     */
    public function isExperiment(string $name)
    {
        $this->pageView();

        if (!$experiment = $this->getExperiment()) {
            return false;
        }

        return $experiment->name === $name;
    }

    /**
     * Completes a goal by incrementing the hit property of the model and setting its ID in the session.
     *
     * @param  string  $goal  The goals name
     * @return \Apurbajnu\abtest\Models\Goal|false
     */
    public function completeGoal(string $goal)
    {
        $this->pageView();

        if (!$this->getExperiment()) {
            return false;
        }

        $goal = $this->getExperiment()->goals->where('name', $goal)->first();

        if (!$goal) {
            return false;
        }

        if (session(self::SESSION_KEY_GOALS)->contains($goal->id)) {
            return false;
        }

        session(self::SESSION_KEY_GOALS)->push($goal->id);

        $goal->incrementHit();
        event(new GoalCompleted($goal));

        return $goal;
    }

    /**
     * Returns the currently active experiment.
     *
     * @return \Apurbajnu\abtest\Models\Experiment|null
     */
    public function getExperiment()
    {
        return session(self::SESSION_KEY_EXPERIMENT);
    }

    /**
     * Returns all the completed goals.
     *
     * @return \Illuminate\Support\Collection|false
     */
    public function getCompletedGoals()
    {
        if (!session(self::SESSION_KEY_GOALS)) {
            return false;
        }

        return session(self::SESSION_KEY_GOALS)->map(function ($goalId) {
            return Goal::find($goalId);
        });
    }
}
