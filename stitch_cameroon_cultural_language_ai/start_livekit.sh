#!/bin/bash

# Script de démarrage du serveur LiveKit pour Mboa

echo "🚀 Démarrage du serveur LiveKit..."

# Vérifier si le binaire LiveKit existe
LIVEKIT_BINARY="./livekit_1.9.0_linux_amd64"

if [ ! -f "$LIVEKIT_BINARY" ]; then
    echo "❌ Erreur: Le binaire LiveKit n'est pas trouvé à $LIVEKIT_BINARY"
    echo "   Veuillez télécharger le binaire depuis https://livekit.io/downloads"
    exit 1
fi

# Rendre le binaire exécutable
chmod +x "$LIVEKIT_BINARY"

# Vérifier si le fichier de configuration existe
if [ ! -f "livekit.yaml" ]; then
    echo "❌ Erreur: Le fichier livekit.yaml n'est pas trouvé"
    exit 1
fi

# Démarrer le serveur
echo "📡 Serveur LiveKit démarré sur ws://localhost:7880"
echo "📝 Logs: livekit.log"
echo "⏹️  Appuyez sur Ctrl+C pour arrêter"

# Lancer LiveKit en arrière-plan avec logs
./"$LIVEKIT_BINARY" --config livekit.yaml 2>&1 | tee livekit.log
