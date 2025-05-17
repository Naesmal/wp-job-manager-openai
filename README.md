# WP Job Manager OpenAI Extension

Extension WordPress pour automatiser la catégorisation et l'enrichissement des offres d'emploi sur [The Good Feat](https://www.thegoodfeat.com) avec l'IA d'OpenAI.

## 📋 Description

Cette extension ajoute une intégration OpenAI à WP Job Manager pour automatiser le traitement des offres d'emploi, en particulier celles importées via GoFetchJobs. 

L'extension utilise les modèles GPT d'OpenAI pour :
- Vérifier et corriger le pays et la région de l'offre
- Catégoriser automatiquement les offres selon les thématiques spécifiques
- Déterminer le type de consultance
- Extraire les liens de candidature et emails de contact
- Détecter les dates de clôture
- Identifier si le poste est en remote, hybrid ou global

## ✨ Fonctionnalités

- **Traitement automatique des offres** lors de leur création ou mise à jour
- **Traitement manuel** possible via un bouton sur le tableau de bord des offres
- **Catégorisation intelligente** selon la hiérarchie de catégories spécifique à The Good Feat
- **Correction du pays** grâce à l'analyse du contenu de l'offre
- **Configuration personnalisée** des paramètres OpenAI (modèle, tokens, température)
- **Support multilingue** pour le traitement d'offres en français, anglais, espagnol et arabe
- **Intégration avec GoFetchJobs** pour le traitement des offres importées

## 🛠️ Installation

1. Téléchargez le ZIP de l'extension
2. Allez dans WordPress > Extensions > Ajouter > Charger une extension
3. Uploadez le fichier ZIP et activez l'extension
4. Configurez votre clé API OpenAI dans WP Job Manager > OpenAI Settings

## ⚙️ Configuration

1. Obtenez une clé API OpenAI sur [la plateforme OpenAI](https://platform.openai.com/api-keys)
2. Dans WordPress, naviguez vers WP Job Manager > OpenAI Settings
3. Entrez votre clé API et configurez les paramètres selon vos besoins :
   - **Modèle OpenAI** : Choisissez entre GPT-3.5 Turbo (recommandé) ou GPT-4 (plus puissant mais plus coûteux)
   - **Tokens maximum** : Limite de tokens pour chaque requête API (affecte le coût)
   - **Température** : Contrôle la créativité des réponses (valeur basse = plus précis)
   - **Traitement automatique** : Activer/désactiver le traitement automatique des offres

## 🔄 Processus de traitement

Pour chaque offre d'emploi, l'extension exécute les tâches suivantes :

### 1. Vérification et correction du pays
- Analyse le champ pays et le contenu de l'offre
- Corrige les valeurs invalides (ex. noms d'organisation au lieu d'un pays)
- Détermine si l'offre est Global, Remote ou Hybrid

### 2. Catégorisation thématique
- Analyse le titre et le contenu de l'offre
- Utilise les sous-catégories comme guide pour la classification
- Assigne l'offre à une ou plusieurs grandes catégories visibles sur le site

### 3. Détermination du type de consultance
- Analyse les sections "Profil", "Modalités", "Comment postuler"
- Détermine automatiquement les caractéristiques de la consultance :
  - Temps plein / Temps partiel
  - Consultant individuel / équipe
  - Ouvert aux firmes / aux ONG
  - National / International
  - Short term / Long term
  - Entry level / Mid-level / Senior level

### 4. Extraction du lien de candidature
- Extrait un lien URL ou une adresse email pour la candidature
- Priorise les liens à la fin de l'annonce

### 5. Détection de la date de clôture
- Extrait la date limite de candidature
- La convertit au format standardisé pour le site

### 6. Détection du mode de travail
- Identifie si le poste est en remote
- Met à jour les métadonnées correspondantes

## 🔍 Traitement manuel

Si vous souhaitez traiter manuellement une offre, un bouton "Traiter avec IA" est disponible dans le tableau de bord des offres d'emploi.

## 📊 Logging et débogage

L'extension enregistre les informations de traitement dans les métadonnées de chaque offre :
- `_ai_processed` : Indique si l'offre a été traitée par l'IA
- `_ai_processed_date` : Date du dernier traitement
- `_ai_processing_error` : Erreurs éventuelles lors du traitement
- `_ai_category_explanation` : Explication de la catégorisation

## 📝 Notes techniques

- L'extension nécessite WP Job Manager 
- Compatibilité testée avec WordPress 6.4+
- Coût d'utilisation dépendant de votre volume d'offres et du modèle OpenAI choisi

## 📊 Estimation des coûts

L'utilisation de l'API OpenAI a un coût qui dépend de plusieurs facteurs :

- **Volume d'offres** : Nombre d'offres traitées
- **Modèle utilisé** : GPT-3.5 Turbo est significativement moins cher que GPT-4
- **Longueur des offres** : Plus l'offre est longue, plus le coût est élevé

### Estimation par offre :

| Modèle | Coût approximatif par offre |
|--------|---------------------------|
| GPT-3.5-Turbo | 0,05€ - 0,15€ |
| GPT-4 | 0,15€ - 0,50€ |

*Les prix sont donnés à titre indicatif et peuvent varier selon les tarifs OpenAI et la longueur des offres.*

## 🔧 Personnalisation

L'extension est conçue pour être facilement personnalisable. Les développeurs peuvent :

- Modifier la liste des catégories et sous-catégories
- Ajuster les prompts envoyés à l'API OpenAI
- Ajouter de nouveaux champs à analyser
- Intégrer d'autres sources d'importation d'offres

## 📞 Support et Contact

Pour toute question concernant l'extension ou pour signaler un problème :

- Créez une issue sur GitHub
- Contactez directement The Good Feat pour un support personnalisé

## 🚀 Évolutions futures

- Ajout d'une interface d'analyse des coûts d'utilisation
- Prise en charge de nouveaux modèles d'OpenAI
- Option de prévisualisation avant application des changements
- Harmonisation automatique de la mise en page des offres

## 📄 Licence

Cette extension est distribuée sous licence GPL-2.0+, comme WordPress et WP Job Manager.

## 🙏 Remerciements

- [OpenAI](https://openai.com/) pour leur API GPT
- [WP Job Manager](https://wpjobmanager.com/) pour leur excellent plugin de gestion d'offres d'emploi
- L'équipe de The Good Feat pour leurs spécifications détaillées

---

Développé par The Good Feat © 2025