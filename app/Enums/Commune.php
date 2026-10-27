<?php

namespace App\Enums;

/**
 * The 52 communes of Chile's Región Metropolitana, where a studio can be. A
 * fixed list keeps the data exact (no "Ñuñoa" vs "Nunoa" duplicates).
 */
enum Commune: string
{
    case Alhue = 'alhue';
    case Buin = 'buin';
    case CaleraDeTango = 'calera_de_tango';
    case Cerrillos = 'cerrillos';
    case CerroNavia = 'cerro_navia';
    case Colina = 'colina';
    case Conchali = 'conchali';
    case Curacavi = 'curacavi';
    case ElBosque = 'el_bosque';
    case ElMonte = 'el_monte';
    case EstacionCentral = 'estacion_central';
    case Huechuraba = 'huechuraba';
    case Independencia = 'independencia';
    case IslaDeMaipo = 'isla_de_maipo';
    case LaCisterna = 'la_cisterna';
    case LaFlorida = 'la_florida';
    case LaGranja = 'la_granja';
    case LaPintana = 'la_pintana';
    case LaReina = 'la_reina';
    case Lampa = 'lampa';
    case LasCondes = 'las_condes';
    case LoBarnechea = 'lo_barnechea';
    case LoEspejo = 'lo_espejo';
    case LoPrado = 'lo_prado';
    case Macul = 'macul';
    case Maipu = 'maipu';
    case MariaPinto = 'maria_pinto';
    case Melipilla = 'melipilla';
    case Nunoa = 'nunoa';
    case PadreHurtado = 'padre_hurtado';
    case Paine = 'paine';
    case PedroAguirreCerda = 'pedro_aguirre_cerda';
    case Penaflor = 'penaflor';
    case Penalolen = 'penalolen';
    case Pirque = 'pirque';
    case Providencia = 'providencia';
    case Pudahuel = 'pudahuel';
    case PuenteAlto = 'puente_alto';
    case Quilicura = 'quilicura';
    case QuintaNormal = 'quinta_normal';
    case Recoleta = 'recoleta';
    case Renca = 'renca';
    case SanBernardo = 'san_bernardo';
    case SanJoaquin = 'san_joaquin';
    case SanJoseDeMaipo = 'san_jose_de_maipo';
    case SanMiguel = 'san_miguel';
    case SanPedro = 'san_pedro';
    case SanRamon = 'san_ramon';
    case Santiago = 'santiago';
    case Talagante = 'talagante';
    case Tiltil = 'tiltil';
    case Vitacura = 'vitacura';

    /**
     * Official name, with accents, shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Alhue => 'Alhué',
            self::Buin => 'Buin',
            self::CaleraDeTango => 'Calera de Tango',
            self::Cerrillos => 'Cerrillos',
            self::CerroNavia => 'Cerro Navia',
            self::Colina => 'Colina',
            self::Conchali => 'Conchalí',
            self::Curacavi => 'Curacaví',
            self::ElBosque => 'El Bosque',
            self::ElMonte => 'El Monte',
            self::EstacionCentral => 'Estación Central',
            self::Huechuraba => 'Huechuraba',
            self::Independencia => 'Independencia',
            self::IslaDeMaipo => 'Isla de Maipo',
            self::LaCisterna => 'La Cisterna',
            self::LaFlorida => 'La Florida',
            self::LaGranja => 'La Granja',
            self::LaPintana => 'La Pintana',
            self::LaReina => 'La Reina',
            self::Lampa => 'Lampa',
            self::LasCondes => 'Las Condes',
            self::LoBarnechea => 'Lo Barnechea',
            self::LoEspejo => 'Lo Espejo',
            self::LoPrado => 'Lo Prado',
            self::Macul => 'Macul',
            self::Maipu => 'Maipú',
            self::MariaPinto => 'María Pinto',
            self::Melipilla => 'Melipilla',
            self::Nunoa => 'Ñuñoa',
            self::PadreHurtado => 'Padre Hurtado',
            self::Paine => 'Paine',
            self::PedroAguirreCerda => 'Pedro Aguirre Cerda',
            self::Penaflor => 'Peñaflor',
            self::Penalolen => 'Peñalolén',
            self::Pirque => 'Pirque',
            self::Providencia => 'Providencia',
            self::Pudahuel => 'Pudahuel',
            self::PuenteAlto => 'Puente Alto',
            self::Quilicura => 'Quilicura',
            self::QuintaNormal => 'Quinta Normal',
            self::Recoleta => 'Recoleta',
            self::Renca => 'Renca',
            self::SanBernardo => 'San Bernardo',
            self::SanJoaquin => 'San Joaquín',
            self::SanJoseDeMaipo => 'San José de Maipo',
            self::SanMiguel => 'San Miguel',
            self::SanPedro => 'San Pedro',
            self::SanRamon => 'San Ramón',
            self::Santiago => 'Santiago',
            self::Talagante => 'Talagante',
            self::Tiltil => 'Tiltil',
            self::Vitacura => 'Vitacura',
        };
    }

    /**
     * Choices for a select input, in declaration order.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $commune): array => ['value' => $commune->value, 'label' => $commune->label()],
            self::cases(),
        );
    }
}
