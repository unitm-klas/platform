<?php

namespace Oro\Bundle\EntityConfigBundle\Layout\Block\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Layout\AttributeGroupRenderRegistry;
use Oro\Bundle\EntityConfigBundle\Layout\Mapper\AttributeBlockTypeMapperInterface;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;

use Oro\Component\Layout\Block\OptionsResolver\OptionsResolver;
use Oro\Component\Layout\Block\Type\AbstractContainerType;
use Oro\Component\Layout\Block\Type\Options;
use Oro\Component\Layout\BlockBuilderInterface;
use Oro\Component\Layout\BlockInterface;
use Oro\Component\Layout\BlockView;
use Oro\Component\Layout\Util\BlockUtils;

class AttributeGroupType extends AbstractContainerType
{
    const NAME = 'attribute_group';

    /** @var AttributeGroupRenderRegistry */
    protected $groupRenderRegistry;

    /** @var AttributeBlockTypeMapperInterface */
    protected $blockTypeMapper;

    /** @var AttributeManager */
    protected $attributeManager;

    /** @var array */
    protected $notRenderableAttributeTypes = [];

    /**
     * @param AttributeGroupRenderRegistry      $groupRenderRegistry
     * @param AttributeManager                  $attributeManager
     * @param AttributeBlockTypeMapperInterface $blockTypeMapper
     */
    public function __construct(
        AttributeGroupRenderRegistry $groupRenderRegistry,
        AttributeManager $attributeManager,
        AttributeBlockTypeMapperInterface $blockTypeMapper
    ) {
        $this->groupRenderRegistry = $groupRenderRegistry;
        $this->attributeManager = $attributeManager;
        $this->blockTypeMapper = $blockTypeMapper;
    }

    /**
     * @param array $notRenderableAttributeTypes
     */
    public function setNotRenderableAttributeTypes(array $notRenderableAttributeTypes)
    {
        $this->notRenderableAttributeTypes = $notRenderableAttributeTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function buildBlock(BlockBuilderInterface $builder, Options $options)
    {
        /** @var AttributeFamily $attributeFamily */
        $attributeFamily = $options->get('attribute_family');
        $code = $options['group'];
        $entityValue = $options->get('entity', false);
        $excludeFromRest = $options['exclude_from_rest'];

        $attributeGroups = $attributeFamily->getAttributeGroups()->filter(
            function (AttributeGroup $attributeGroup) use ($code) {
                return $attributeGroup->getCode() == $code;
            }
        );

        if ($attributeGroups->count() === 0) {
            return;
        }

        /** @var AttributeGroup $attributeGroup */
        $attributeGroup = $attributeGroups->first();
        $options['visible'] = $attributeGroup->getIsVisible();

        if ($excludeFromRest) {
            $this->groupRenderRegistry->setRendered($attributeFamily, $attributeGroup);
        }

        $layoutManipulator = $builder->getLayoutManipulator();
        $attributeGroupBlockId = $builder->getId();
        $attributes = $this->attributeManager->getAttributesByGroup($attributeGroup);
        foreach ($attributes as $attribute) {
            if (in_array($attribute->getType(), $this->notRenderableAttributeTypes, true)) {
                continue;
            }

            $fieldName = $attribute->getFieldName();
            $blockType = $this->blockTypeMapper->getBlockType($attribute);
            $layoutManipulator->add(
                $this->getAttributeBlockName($fieldName, $blockType, $attributeGroupBlockId),
                $attributeGroupBlockId,
                $blockType,
                array_merge(
                    [
                        'entity' => $entityValue,
                        'fieldName' => $attribute->getFieldName(),
                        'className' => $attribute->getEntity()->getClassName()
                    ],
                    $options->get('attribute_options')->toArray()
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(BlockView $view, BlockInterface $block, Options $options)
    {
        BlockUtils::setViewVarsFromOptions($view, $options, ['group']);
    }

    /**
     * @param string $field_name
     * @param string $blockType
     * @param string $attributeGroupBlockId
     *
     * @return string
     */
    private function getAttributeBlockName($field_name, $blockType, $attributeGroupBlockId)
    {
        return sprintf('%s_%s_%s', $attributeGroupBlockId, $blockType, $field_name);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(
            [
                'group',
                'entity',
                'attribute_family'
            ]
        );
        $resolver->setDefaults(
            [
                'exclude_from_rest' => true,
                'attribute_options' => []
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}
