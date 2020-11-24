<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/*
 * https://docs.phalconphp.com/3.4/ru-ru/db-models-metadata
 *
 */


namespace Modules\ModulePT1CCore\Models;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Modules\Models\ModulesModelsBase;
use Phalcon\Mvc\Model\Relation;

class ModulePT1CCore extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Extension, перехват на ответсвенного
     *
     * @Column(type="string", nullable=true, default="10000104")
     */
    public $interception_extension;

    /**
     * Extension, создание конференции
     *
     * @Column(type="string", nullable=true, default="10000107")
     */
    public $conference_extension;

    /**
     * Extension, получение контекста и технологии пира
     *
     * @Column(type="string", nullable=true, default="10000109")
     */
    public $get_peer_info_extension;

    /**
     * Extension, авторизация панели
     *
     * @Column(type="string", nullable=true, default="10000111")
     */
    public $get_auth_extension;

    /**
     * Extension, работа со статусами в astdb.
     *
     * @Column(type="string", nullable=true, default="10000222")
     */
    public $get_statuses_extension;

    /**
     *  Extension, который возвращяет историю
     *
     * @Column(type="string", nullable=true, default="10000555")
     */
    public $get_cdr_extension;

    /**
     * Extension, обработка запроса прослушивания
     *
     * @Column(type="string", nullable=true, default="10000666")
     */
    public $listen_recordings_extension;

    /**
     * Extension, обработка запроса скачивания
     *
     * @Column(type="string", nullable=true, default="10000777")
     */
    public $get_recordings_extension;

    /**
     * Returns dynamic relations between module models and common models
     * MikoPBX check it in ModelsBase after every call to keep data consistent
     *
     * There is example to describe the relation between Providers and ModuleTemplate models
     *
     * It is important to duplicate the relation alias on message field after Models\ word
     *
     * @param $calledModelObject
     *
     * @return void
     */
    public static function getDynamicRelations(&$calledModelObject): void
    {
        if (is_a($calledModelObject, Extensions::class)) {
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'interception_extension',
                [
                    'alias'      => 'ModulePT1CCoreInterception',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreInterception',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'conference_extension',
                [
                    'alias'      => 'ModulePT1CCoreConference',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreConference',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'get_peer_info_extension',
                [
                    'alias'      => 'ModulePT1CCoreGetPeerInfo',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreGetPeerInfo',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'get_auth_extension',
                [
                    'alias'      => 'ModulePT1CCoreGetAuth',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreGetAuth',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'get_statuses_extension',
                [
                    'alias'      => 'ModulePT1CCoreGetStatuses',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreGetStatuses',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );


            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'get_cdr_extension',
                [
                    'alias'      => 'ModulePT1CCoreGetCDR',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreGetCDR',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'listen_recordings_extension',
                [
                    'alias'      => 'ModulePT1CCoreListenRecordings',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreListenRecordings',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );

            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'get_recordings_extension',
                [
                    'alias'      => 'ModulePT1CCoreGetRecordings',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModulePT1CCoreGetRecordings',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
        }
    }

    public function initialize(): void
    {
        $this->setSource('m_ModulePT1CCore');
        parent::initialize();
        $this->hasOne(
            'interception_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsInterception',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'conference_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsConference',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'get_peer_info_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsGetPeerInfo',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'get_auth_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsGetAuth',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'get_statuses_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsGetStatuses',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'get_cdr_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsGetCDR',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'listen_recordings_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsListenRecords',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
        $this->hasOne(
            'get_recordings_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsGetRecFile',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
    }


}
