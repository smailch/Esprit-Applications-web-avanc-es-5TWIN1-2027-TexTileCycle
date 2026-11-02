# Module Messagerie (Module 5 — Administration)

Échanges entre **l'équipe d'administration** et les **citoyens** ou les **responsables d'ateliers et d'associations**. Il n'y a pas de messagerie entre citoyens.

## Fonctionnement

- **Boîte partagée :** tous les administrateurs voient toutes les conversations (`/admin/messagerie`).
- **Côté utilisateur :** chaque utilisateur ne voit que les siennes (`/messages`). Un citoyen les retrouve dans le gabarit « trame », un partenaire dans son back-office.
- **Rafraîchissement :** polling AJAX (`public/js/messagerie.js`), sans WebSocket ni service externe.
  - Le fil ouvert récupère les nouveaux messages toutes les 4 s.
  - Les badges de non-lus se mettent à jour toutes les 30 s.
- **Messages non lus :** chaque conversation garde deux compteurs, `non_lus_admin` et `non_lus_utilisateur`. Le compteur d'un côté est remis à zéro quand ce côté lit la conversation. Les dates `lu_admin_at` / `lu_utilisateur_at` donnent l'accusé « Lu ».
- **Conversation fermée :** l'admin peut la fermer. Plus personne ne peut alors y répondre, jusqu'à ce qu'il la rouvre.

## Entités et relations

| Modèle | Relations |
|---|---|
| `Conversation` | `interlocuteur()` et `admin()` → User · `messages()` hasMany · `contexte()` morphTo (Signalement, Atelier, Association) |
| `Message` | `conversation()` · `auteur()` → User |

## CRUD Conversation (admin)

| Action | Route |
|---|---|
| Liste (filtres non lues / ouvertes / fermées, rôle, recherche) | `GET /admin/messagerie` |
| Ajout | `GET /admin/messagerie/nouvelle`, `POST /admin/messagerie` |
| Détail (fil) | `GET /admin/messagerie/{id}` |
| Modification (sujet, statut) | `GET /admin/messagerie/{id}/modifier`, `PUT …` |
| Suppression (avec confirmation, supprime aussi les messages) | `DELETE /admin/messagerie/{id}` |
| Polling / envoi | `GET` et `POST /admin/messagerie/{id}/messages` |

On peut ouvrir une conversation déjà remplie depuis :
- le **détail d'un signalement** (« Contacter l'auteur ») ;
- la page **Partenaires** (« Contacter »).

## Sécurité

- `ConversationPolicy` : seul l'interlocuteur ou un admin peut voir ou répondre ; seul un admin peut modifier ou supprimer.
- Limites d'envoi : 30 messages par minute, 10 nouvelles conversations par minute.
- Le contenu n'est jamais interprété comme du HTML (`{{ }}` dans Blade, `textContent` en JS).

## Données de démo

```bash
php artisan migrate                                                            # index conversations / messages
php artisan db:seed --class="App\Modules\Messagerie\Database\Seeders\MessagerieSeeder"
```

`ConversationFactory::avecEchange([...])` crée les messages liés et recalcule l'aperçu et les compteurs de non-lus.
