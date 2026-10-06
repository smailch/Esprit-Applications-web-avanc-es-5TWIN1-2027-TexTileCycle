<?php

namespace Tests\Concerns;

use Illuminate\Validation\DatabasePresenceVerifier;
use Jenssegers\Mongodb\Query\Builder as MongoBuilder;
use MongoDB\BSON\ObjectId;

/**
 * Vrai DatabasePresenceVerifier de Laravel dont seul count() est évalué en mémoire, à partir des
 * filtres Mongo compilés (égalité stricte, $ne, $and). Les _id sont comparés comme ObjectId.
 */
trait FakesMongoPresenceVerifier
{
    /**
     * @param  array<string, string>  $emailsParId  ObjectId (chaîne) => e-mail tel qu'enregistré
     */
    protected function presenceVerifierEnMemoire(array $emailsParId): DatabasePresenceVerifier
    {
        return new class(app('db'), $emailsParId) extends DatabasePresenceVerifier
        {
            public function __construct($db, private array $utilisateurs)
            {
                parent::__construct($db);
            }

            protected function table($table)
            {
                $connexion = $this->db->connection($this->connection);

                return (new class($connexion, $connexion->getPostProcessor(), $this->utilisateurs) extends MongoBuilder
                {
                    public function __construct($connexion, $processor, private array $utilisateurs)
                    {
                        parent::__construct($connexion, $processor);
                    }

                    public function count($columns = '*')
                    {
                        $filtre = $this->compileWheres();
                        $total = 0;

                        foreach ($this->utilisateurs as $id => $email) {
                            $total += (int) $this->correspond($filtre, ['_id' => new ObjectId($id), 'email' => $email]);
                        }

                        return $total;
                    }

                    private function correspond(array $filtre, array $document): bool
                    {
                        foreach ($filtre as $cle => $condition) {
                            if ($cle === '$and') {
                                foreach ($condition as $sousFiltre) {
                                    if (! $this->correspond($sousFiltre, $document)) {
                                        return false;
                                    }
                                }

                                continue;
                            }

                            $valeur = $document[$cle] ?? null;

                            if (is_array($condition) && array_key_exists('$ne', $condition)) {
                                if ($this->egal($valeur, $condition['$ne'])) {
                                    return false;
                                }

                                continue;
                            }

                            if (! $this->egal($valeur, $condition)) {
                                return false;
                            }
                        }

                        return true;
                    }

                    private function egal(mixed $a, mixed $b): bool
                    {
                        if ($a instanceof ObjectId || $b instanceof ObjectId) {
                            return $a instanceof ObjectId && $b instanceof ObjectId && (string) $a === (string) $b;
                        }

                        return $a === $b;
                    }
                })->from($table);
            }
        };
    }
}
