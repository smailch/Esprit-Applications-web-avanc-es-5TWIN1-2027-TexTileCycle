@include('back.ateliers.partials.field', ['name' => 'nom', 'label' => "Nom de l'atelier", 'value' => old('nom', $atelier->nom), 'required' => true, 'attrs' => ['maxlength' => 120, 'autocomplete' => 'organization']])

@include('back.ateliers.partials.field', ['name' => 'specialite', 'label' => 'Spécialité', 'value' => old('specialite', $atelier->specialite), 'hint' => 'Affichée sur les cartes du site (ex. « Denim & retouches »). Vide : les deux premiers services sont affichés.', 'attrs' => ['maxlength' => 120]])

@include('back.ateliers.partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'value' => old('description', $atelier->description), 'attrs' => ['maxlength' => 2000]])

@include('back.ateliers.partials.field', ['name' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'value' => old('telephone', $atelier->telephone), 'hint' => 'Format libre, ex. +216 71 774 210.', 'attrs' => ['maxlength' => 30, 'autocomplete' => 'tel', 'inputmode' => 'tel']])
